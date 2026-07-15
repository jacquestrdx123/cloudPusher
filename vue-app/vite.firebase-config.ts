import fs from 'node:fs'
import path from 'node:path'
import type { Plugin } from 'vite'

type FirebaseWebConfig = {
  apiKey?: string
  authDomain?: string
  projectId?: string
  storageBucket?: string
  messagingSenderId?: string
  appId?: string
  measurementId?: string
}

function loadFirebaseJson(root: string): FirebaseWebConfig {
  const file = path.resolve(root, 'firebase.json')

  if (!fs.existsSync(file)) {
    return {}
  }

  return JSON.parse(fs.readFileSync(file, 'utf8')) as FirebaseWebConfig
}

export function firebaseConfigPlugin(): Plugin {
  const writeConfig = (root: string): void => {
    const fromFile = loadFirebaseJson(root)
    const config = {
      apiKey: process.env.VITE_FIREBASE_API_KEY || fromFile.apiKey || '',
      authDomain: process.env.VITE_FIREBASE_AUTH_DOMAIN || fromFile.authDomain || '',
      projectId: process.env.VITE_FIREBASE_PROJECT_ID || fromFile.projectId || '',
      storageBucket: process.env.VITE_FIREBASE_STORAGE_BUCKET || fromFile.storageBucket || '',
      messagingSenderId:
        process.env.VITE_FIREBASE_MESSAGING_SENDER_ID || fromFile.messagingSenderId || '',
      appId: process.env.VITE_FIREBASE_APP_ID || fromFile.appId || '',
      measurementId: process.env.VITE_FIREBASE_MEASUREMENT_ID || fromFile.measurementId || '',
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
