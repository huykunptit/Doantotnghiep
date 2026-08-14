export interface AiChatHistoryMsg {
  role: 'user' | 'assistant'
  text: string
  sources?: Array<{ source?: string, subject?: string | null, score?: number | null }>
  ragUsed?: boolean
}

const PREFIX = 'eript-ai-chat:'
const MAX_MESSAGES = 50

function storageKey(id: string) {
  return PREFIX + id
}

export function useAiChatHistory(key: MaybeRefOrGetter<string>) {
  function read(): AiChatHistoryMsg[] | null {
    if (!import.meta.client) return null
    try {
      const raw = localStorage.getItem(storageKey(toValue(key)))
      if (!raw) return null
      const parsed = JSON.parse(raw) as unknown
      if (!Array.isArray(parsed)) return null
      const rows = parsed
        .filter((row): row is AiChatHistoryMsg =>
          !!row && (row.role === 'user' || row.role === 'assistant') && typeof row.text === 'string',
        )
        .filter(row => row.text.trim() !== '')
        .slice(-MAX_MESSAGES)
      return rows.length ? rows : null
    }
    catch {
      return null
    }
  }

  function write(messages: AiChatHistoryMsg[]) {
    if (!import.meta.client) return
    try {
      const compact = messages
        .filter(row => row.text.trim() !== '')
        .slice(-MAX_MESSAGES)
        .map(row => ({
          role: row.role,
          text: row.text,
          ragUsed: row.ragUsed,
          sources: row.sources,
        }))
      localStorage.setItem(storageKey(toValue(key)), JSON.stringify(compact))
    }
    catch {
      // quota / private mode
    }
  }

  function clear() {
    if (!import.meta.client) return
    try {
      localStorage.removeItem(storageKey(toValue(key)))
    }
    catch {
      // ignore
    }
  }

  return { read, write, clear }
}
