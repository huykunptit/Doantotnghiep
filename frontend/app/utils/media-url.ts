export function resolveMediaUrl(url?: string | null): string | undefined {
  if (!url) return undefined
  const trimmed = url.trim()
  if (!trimmed) return undefined
  if (trimmed.startsWith('data:') || trimmed.startsWith('blob:')) return trimmed

  if (/^https?:\/\//i.test(trimmed)) {
    try {
      const parsed = new URL(trimmed)
      const host = parsed.hostname.toLowerCase()
      const internal = [
        'localhost',
        '127.0.0.1',
        'backend',
        'nginx',
        'minio',
        'host.docker.internal',
      ].includes(host) || host.includes('minio')
      const sameHost = import.meta.client && host === window.location.hostname
      const minioPort = parsed.port === '9000' || parsed.port === '9001'

      if (minioPort || host === 'minio' || host.includes('minio')) {
        return `/minio${parsed.pathname}`
      }
      if (internal || sameHost) {
        return `${parsed.pathname}${parsed.search}`
      }
      return trimmed
    }
    catch {
      return trimmed
    }
  }

  if (
    trimmed.startsWith('/storage/')
    || trimmed.startsWith('/minio/')
    || trimmed.startsWith('/uploads/')
    || trimmed.startsWith('/images/')
  ) {
    return trimmed
  }

  if (trimmed.startsWith('storage/')) return `/${trimmed}`
  return `/storage/${trimmed.replace(/^\//, '')}`
}
