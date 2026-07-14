<?php

namespace App\Filament\Pages;

use App\Actions\DispatchPushNotification;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use App\Models\UserGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * @property-read Schema $form
 */
class SendNotification extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Send notification';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'send-notification';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'target_type' => PushNotification::TARGET_USER,
            'channels' => ['push', 'mail'],
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Send notification';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ])
                    ->id('send-notification-form')
                    ->livewireSubmitHandler('send')
                    ->footer([
                        Actions::make([
                            Action::make('send')
                                ->label('Queue notification')
                                ->submit('send'),
                        ]),
                    ]),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('target_type')
                    ->label('Audience')
                    ->options([
                        PushNotification::TARGET_USER => 'Single user',
                        PushNotification::TARGET_GROUP => 'User group',
                        PushNotification::TARGET_BROADCAST => 'All company users',
                    ])
                    ->required()
                    ->live(),
                Select::make('user_id')
                    ->label('User')
                    ->options(fn (): array => User::query()
                        ->when(
                            ($tenant = Filament::getTenant()) !== null,
                            fn ($query) => $query->where('company_id', $tenant->getKey()),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(fn (callable $get): bool => $get('target_type') === PushNotification::TARGET_USER)
                    ->visible(fn (callable $get): bool => $get('target_type') === PushNotification::TARGET_USER),
                Select::make('user_group_id')
                    ->label('Group')
                    ->options(fn (): array => UserGroup::query()
                        ->when(
                            ($tenant = Filament::getTenant()) !== null,
                            fn ($query) => $query->where('company_id', $tenant->getKey()),
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(fn (callable $get): bool => $get('target_type') === PushNotification::TARGET_GROUP)
                    ->visible(fn (callable $get): bool => $get('target_type') === PushNotification::TARGET_GROUP),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('body')
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Select::make('channels')
                    ->multiple()
                    ->options([
                        'push' => 'Push (FCM / APNs)',
                        'mail' => 'Email',
                        'sms' => 'SMS',
                    ])
                    ->required(),
                DateTimePicker::make('scheduled_at')
                    ->label('Schedule for later')
                    ->minDate(now())
                    ->seconds(false),
            ])
            ->statePath('data');
    }

    public function send(DispatchPushNotification $dispatchPushNotification): void
    {
        $company = Filament::getTenant();

        if (! $company instanceof Company) {
            Notification::make()
                ->title('Select a company first')
                ->danger()
                ->send();

            return;
        }

        $state = $this->form->getState();
        $targetType = $state['target_type'];

        $target = ['type' => $targetType];

        if ($targetType === PushNotification::TARGET_USER) {
            $target['id'] = (int) $state['user_id'];
        }

        if ($targetType === PushNotification::TARGET_GROUP) {
            $target['id'] = (int) $state['user_group_id'];
        }

        $payload = [
            'target' => $target,
            'title' => $state['title'],
            'body' => $state['body'] ?? null,
            'channels' => $state['channels'],
        ];

        if (! empty($state['scheduled_at'])) {
            $payload['scheduled_at'] = $state['scheduled_at'];
        }

        $notification = $dispatchPushNotification->handle($company, $payload);

        Notification::make()
            ->title('Notification queued')
            ->body("Status: {$notification->status}")
            ->success()
            ->send();

        $this->form->fill([
            'target_type' => PushNotification::TARGET_USER,
            'channels' => ['push', 'mail'],
        ]);
    }
}
