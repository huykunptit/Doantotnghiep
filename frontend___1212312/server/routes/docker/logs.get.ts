import { exec } from 'node:child_process'
import { promisify } from 'node:util'
import { getQuery } from 'h3'

const execAsync = promisify(exec)

const SUDO_PASS = process.env.DOCKER_SUDO_PASS || '211221'

function sudo(cmd: string) {
  const isRoot = process.getuid?.() === 0
  return isRoot ? cmd : `echo "${SUDO_PASS}" | sudo -S -p "" ${cmd}`
}

const ALLOWED_CONTAINERS = [
  'lms_nginx', 'lms_backend', 'lms_frontend', 'lms_mysql',
  'lms_redis', 'lms_mongodb', 'lms_minio', 'lms_ai_service',
  'lms_n8n', 'lms_phpmyadmin',
]

export default defineEventHandler(async (event) => {
  const { container, lines = '500' } = getQuery(event) as { container: string; lines?: string }

  if (!ALLOWED_CONTAINERS.includes(container)) {
    throw createError({ statusCode: 400, message: `Container invalide: ${container}` })
  }

  const n = Math.min(parseInt(lines) || 500, 2000)

  try {
    const { stdout } = await execAsync(
      sudo(`docker logs --tail ${n} ${container} 2>&1`),
      { timeout: 15_000, shell: '/bin/sh' }
    )
    return { logs: stdout || '(Không có log)', lines: n }
  } catch (e: any) {
    const msg = (e?.stderr || e?.stdout || e?.message || 'Không lấy được log').trim()
    return { logs: msg, lines: n }
  }
})
