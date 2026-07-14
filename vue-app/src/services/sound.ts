import { Haptics, ImpactStyle } from '@capacitor/haptics'
import { Capacitor } from '@capacitor/core'
import { config } from '@/config/env'

let audio: HTMLAudioElement | null = null
let unlocked = false

export async function unlockAudio(): Promise<void> {
  if (unlocked || typeof Audio === 'undefined') {
    return
  }

  audio = new Audio(config.soundPath)
  audio.preload = 'auto'
  audio.volume = 1

  try {
    await audio.play()
    audio.pause()
    audio.currentTime = 0
    unlocked = true
  } catch {
    // Browser requires a user gesture before audio can play.
  }
}

export async function playNotificationSound(enabled: boolean): Promise<void> {
  if (!enabled) {
    return
  }

  if (typeof Audio === 'undefined') {
    return
  }

  if (!audio) {
    audio = new Audio(config.soundPath)
    audio.preload = 'auto'
  }

  audio.currentTime = 0

  try {
    await audio.play()
  } catch {
    // Ignore autoplay restrictions; native push still plays system sound.
  }

  if (Capacitor.isNativePlatform()) {
    try {
      await Haptics.impact({ style: ImpactStyle.Medium })
    } catch {
      // Haptics optional
    }
  }
}

export async function vibrateAlert(): Promise<void> {
  if (!Capacitor.isNativePlatform()) {
    return
  }

  try {
    await Haptics.notification({ type: 'WARNING' as never })
  } catch {
    // optional
  }
}
