<?php

namespace App\Console\Commands;

use App\Enums\UserRegistrationStatus;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserRegistration;
use App\Support\PhoneNumber;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('app-review:demo
    {phoneOne : Mobile number for the first demo account (the one given to App Review)}
    {phoneTwo : Mobile number for the second demo account (the colleague)}')]
#[Description('Create or refresh the isolated demo company, its two accounts and sample inbox used for App Review')]
class SetupAppReviewDemo extends Command
{
    public const string COMPANY_SLUG = 'app-review-demo';

    public const string COMPANY_NAME = 'Demo Company';

    public function handle(): int
    {
        $phones = [
            PhoneNumber::normalize((string) $this->argument('phoneOne')),
            PhoneNumber::normalize((string) $this->argument('phoneTwo')),
        ];

        if (in_array('', $phones, true) || $phones[0] === $phones[1]) {
            $this->components->error('Provide two different, valid mobile numbers.');

            return self::FAILURE;
        }

        foreach ($phones as $phone) {
            if ($this->belongsToRealUser($phone)) {
                $this->components->error("{$phone} belongs to a user outside the demo company. Choose another number.");

                return self::FAILURE;
            }
        }

        $password = Str::password(16, symbols: false);

        DB::transaction(function () use ($phones, $password): void {
            $company = Company::query()->updateOrCreate(
                ['slug' => self::COMPANY_SLUG],
                ['name' => self::COMPANY_NAME, 'is_active' => true],
            );

            $reviewerOne = $this->demoUser($company, 'Alex Reviewer', 'app-review-1@example.com', $phones[0], $password, isCompanyAdmin: true);
            $reviewerTwo = $this->demoUser($company, 'Sam Colleague', 'app-review-2@example.com', $phones[1], $password);

            $this->removeOtherMembers($company, $reviewerOne, $reviewerTwo);
            $this->resetInbox($company, $reviewerOne, $reviewerTwo);
            $this->resetPendingRegistration($company);
        });

        $this->components->info('Demo company ready: '.self::COMPANY_NAME.' ('.self::COMPANY_SLUG.')');
        $this->components->twoColumnDetail('Account one (App Review sign-in, company admin)', $phones[0]);
        $this->components->twoColumnDetail('Account two (colleague)', $phones[1]);
        $this->components->twoColumnDetail('Password (both accounts)', $password);
        $this->components->warn('The password is shown once and changes every time this command runs.');

        return self::SUCCESS;
    }

    private function belongsToRealUser(string $phone): bool
    {
        return User::query()
            ->where('phone', $phone)
            ->where(fn ($query) => $query
                ->where('is_admin', true)
                ->orWhereHas('companies', fn ($companies) => $companies->where('slug', '!=', self::COMPANY_SLUG)))
            ->exists();
    }

    private function demoUser(
        Company $company,
        string $name,
        string $email,
        string $phone,
        string $password,
        bool $isCompanyAdmin = false,
    ): User {
        $user = User::query()->firstOrNew(['email' => $email]);

        $user->fill(['name' => $name, 'phone' => $phone, 'password' => $password])->save();

        $company->users()->syncWithoutDetaching([$user->id => ['is_company_admin' => $isCompanyAdmin]]);

        return $user;
    }

    /**
     * Remove whoever a previous review run approved or invited into the demo company.
     * Users who also belong to another company only lose their demo membership.
     */
    private function removeOtherMembers(Company $company, User $reviewerOne, User $reviewerTwo): void
    {
        $company->invitations()->delete();

        $others = $company->users()
            ->whereNotIn('users.id', [$reviewerOne->id, $reviewerTwo->id])
            ->withCount('companies')
            ->get();

        foreach ($others as $other) {
            $company->users()->detach($other->id);

            if ($other->companies_count === 1 && ! $other->isGlobalAdmin()) {
                $other->delete();
            }
        }
    }

    /**
     * Leave one pending registration so the Approvals tab has something to approve or reject.
     */
    private function resetPendingRegistration(Company $company): void
    {
        UserRegistration::query()->where('company_id', $company->id)->delete();

        UserRegistration::query()->create([
            'company_id' => $company->id,
            'name' => 'Jordan Applicant',
            'email' => 'app-review-applicant@example.com',
            'phone' => $this->unusedPhone(),
            'password' => Hash::make(Str::random(40)),
            'status' => UserRegistrationStatus::Pending,
        ]);
    }

    /**
     * A number no user has, so approving the applicant can never attach a real person to the demo company.
     */
    private function unusedPhone(): string
    {
        do {
            $phone = '+27000'.random_int(1000000, 9999999);
        } while (User::query()->where('phone', $phone)->exists());

        return $phone;
    }

    /**
     * Replace the demo company's notifications with a tidy sample inbox for both accounts.
     */
    private function resetInbox(Company $company, User $reviewerOne, User $reviewerTwo): void
    {
        $company->userNotifications()->delete();
        $company->pushNotifications()->delete();

        foreach ($this->sampleNotifications($reviewerTwo) as $index => $sample) {
            $sentAt = Carbon::now()->subHours(($index + 1) * 3);

            $pushNotification = new PushNotification([
                'company_id' => $company->id,
                'target_type' => PushNotification::TARGET_BROADCAST,
                'sent_by_user_id' => $sample['sent_by_user_id'] ?? null,
                'title' => $sample['title'],
                'body' => $sample['body'],
                'image_url' => $sample['data']['image'] ?? null,
                'data' => $sample['data'],
                'channels' => ['push'],
                'status' => PushNotification::STATUS_SENT,
                'recipients_count' => 2,
            ]);
            $pushNotification->created_at = $sentAt;
            $pushNotification->updated_at = $sentAt;
            $pushNotification->save();

            foreach ([$reviewerOne, $reviewerTwo] as $recipient) {
                $inbox = new UserNotification([
                    'company_id' => $company->id,
                    'user_id' => $recipient->id,
                    'push_notification_id' => $pushNotification->id,
                    'title' => $sample['title'],
                    'body' => $sample['body'],
                    'data' => $sample['data'],
                    'channel' => 'apn',
                    'delivered_at' => $sentAt,
                    'read_at' => $sample['read'] ? $sentAt : null,
                ]);
                $inbox->created_at = $sentAt;
                $inbox->updated_at = $sentAt;
                $inbox->save();
            }
        }
    }

    /**
     * @return array<int, array{title: string, body: string, data: array<string, mixed>, read: bool, sent_by_user_id?: int}>
     */
    private function sampleNotifications(User $colleague): array
    {
        return [
            [
                'title' => 'Welcome to cloudPusher',
                'body' => 'Notifications from your company arrive here. Tap one to see the details.',
                'data' => ['image' => url('images/app-review-welcome.png')],
                'read' => false,
            ],
            [
                'title' => 'Team meeting moved to 14:00',
                'body' => 'The weekly team meeting now starts at 14:00 in the main boardroom.',
                'data' => ['sender_id' => $colleague->id, 'sender_name' => $colleague->name],
                'read' => false,
                'sent_by_user_id' => $colleague->id,
            ],
            [
                'title' => 'Scheduled maintenance tonight',
                'body' => 'Systems will be unavailable from 22:00 to 23:00 for planned maintenance.',
                'data' => ['url' => url('support'), 'url_label' => 'Get help'],
                'read' => false,
            ],
            [
                'title' => 'Office closed on Friday',
                'body' => 'The office is closed this Friday for the public holiday. Enjoy the long weekend.',
                'data' => [],
                'read' => true,
            ],
            [
                'title' => 'New delivery schedule published',
                'body' => 'The delivery schedule for next week is now available.',
                'data' => [],
                'read' => true,
            ],
        ];
    }
}
