import fs from 'node:fs'
import path from 'node:path'
import type { Plugin } from 'vite'

export function firebaseConfigPlugin(): Plugin {
  const writeConfig = (root: string): void => {
    const config = {
      apiKey: process.env.VITE_FIREBASE_API_KEY ?? '',
      authDomain: process.env.VITE_FIREBASE_AUTH_DOMAIN ?? '',
      projectId: process.env.VITE_FIREBASE_PROJECT_ID ?? '',
      messagingSenderId: process.env.VITE_FIREBASE_MESSAGING_SENDER_ID ?? '',
      appId: process.env.VITE_FIREBASE_APP_ID ?? '',
    }

    const target = path.resolve(root, 'public/firebase-config.js')
    const contents = `self.FIREBASE_CONFIG = ${JSON.stringify(config, null, 2)}\n`

    fs.writeFileSync(target, contents)
  }

  return {
    name: 'firebase-config',
    configResolved(config) {
      writeConfig(config.root)
    },
    buildStart() {
      writeConfig(process.cwd())
    },
  }
}
