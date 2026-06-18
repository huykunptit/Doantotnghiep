import { exec } from 'node:child_process'
import { promisify } from 'node:util'

const execAsync = promisify(exec)

const SUDO_PASS = process.env.DOCKER_SUDO_PASS || '211221'

function sudo(cmd: string) {
  const isRoot = process.getuid?.() === 0
  return isRoot ? cmd : `echo "${SUDO_PASS}" | sudo -S -p "" ${cmd}`
}

const SERVICES = [
  { service: 'nginx',       container: 'lms_nginx',       label: 'Nginx (Reverse Proxy)',   icon: 'router' },
  { service: 'backend',    container: 'lms_backend',     label: 'Backend (Laravel)',        icon: 'dns' },
  { service: 'frontend',   container: 'lms_frontend',    label: 'Frontend (Nuxt)',          icon: 'web' },
  { service: 'mysql',      container: 'lms_mysql',       label: 'MySQL 8',                 icon: 'storage' },
  { service: 'redis',      container: 'lms_redis',       label: 'Redis',                   icon: 'memory' },
  { service: 'mongodb',    container: 'lms_mongodb',     label: 'MongoDB 7',               icon: 'dataset' },
  { service: 'minio',      container: 'lms_minio',       label: 'MinIO (File Storage)',     icon: 'folder_open' },
  { service: 'ai-service', container: 'lms_ai_service',  label: 'AI Service (FastAPI)',     icon: 'psychology' },
  { service: 'n8n',        container: 'lms_n8n',         label: 'N8N (Automation)',         icon: 'account_tree' },
  { service: 'phpmyadmin', container: 'lms_phpmyadmin',  label: 'phpMyAdmin',              icon: 'manage_search' },
]

export default defineEventHandler(async () => {
  try {
    await execAsync(sudo('docker info --format "ok"'), { timeout: 8_000, shell: '/bin/sh' })
  } catch {
    return {
      dockerAvailable: false,
      services: SERVICES.map(s => ({ ...s, state: 'unknown', status: 'Docker daemon not reachable', running: false })),
    }
  }

  let runningMap: Record<string, { state: string; status: string }> = {}
  try {
    const { stdout } = await execAsync(
      sudo('docker ps -a --format "{{.Names}}|{{.State}}|{{.Status}}" 2>/dev/null'),
      { shell: '/bin/sh' }
    )
    for (const line of stdout.trim().split('\n')) {
      if (!line) continue
      const [name, state, ...rest] = line.split('|')
      if (name) runningMap[name.trim()] = { state: (state || '').trim(), status: rest.join('|').trim() }
    }
  } catch {}

  const services = SERVICES.map((s) => {
    const info = runningMap[s.container]
    return {
      ...s,
      state: info?.state || 'not found',
      status: info?.status || 'Container not found',
      running: info?.state === 'running',
    }
  })

  return { dockerAvailable: true, services }
})
