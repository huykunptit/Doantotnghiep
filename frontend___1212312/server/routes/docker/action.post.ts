import { exec } from 'node:child_process'
import { promisify } from 'node:util'
import { readBody } from 'h3'

const execAsync = promisify(exec)

const COMPOSE_FILE = '/home/wetech/Documents/Doantotnghiep/docker-compose.yml'
const SUDO_PASS = process.env.DOCKER_SUDO_PASS || '211221'

const ALLOWED_SERVICES = [
  'nginx', 'backend', 'frontend', 'mysql', 'redis',
  'mongodb', 'minio', 'ai-service', 'n8n', 'phpmyadmin',
]

const ALLOWED_ACTIONS = ['start', 'stop', 'restart'] as const

// Inside production container we run as root → sudo not needed
// On host dev mode the user may need sudo
function sudo(cmd: string) {
  const isRoot = process.getuid?.() === 0
  return isRoot ? cmd : `echo "${SUDO_PASS}" | sudo -S -p "" ${cmd}`
}

// Map frontend action → compose subcommand
const ACTION_CMD: Record<string, string> = {
  start: `up -d --build`,   // rebuild image then bring up
  stop: `stop`,
  restart: `restart`,
}

export default defineEventHandler(async (event) => {
  const body = await readBody(event)
  const { service, action } = body as { service: string; action: string }

  if (!ALLOWED_SERVICES.includes(service)) {
    throw createError({ statusCode: 400, message: `Service invalide: ${service}` })
  }
  if (!ALLOWED_ACTIONS.includes(action as any)) {
    throw createError({ statusCode: 400, message: `Action invalide: ${action}` })
  }

  const subCmd = ACTION_CMD[action]
  const raw = `docker compose -f "${COMPOSE_FILE}" ${subCmd} ${service}`

  try {
    const { stdout, stderr } = await execAsync(sudo(raw), {
      timeout: action === 'start' ? 180_000 : 45_000, // build can take time
      shell: '/bin/sh',
    })
    const out = (stdout + stderr).trim()
    return { success: true, output: out || `${action} ${service} OK` }
  } catch (e: any) {
    const out = (e?.stderr || e?.stdout || e?.message || 'Lỗi không xác định').trim()
    return { success: false, output: out }
  }
})
