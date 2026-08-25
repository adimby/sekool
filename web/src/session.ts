type School = {
  id: string
  name: string
  code: string
  role: string
}

export type Session = {
  token: string
  person_id: string
  person: {
    id: string
    public_id: string
    first_name: string
    last_name: string
  }
  schools: School[]
  is_parent: boolean
  schoolId?: string
}

const KEY = 'fanabe.session'

export function loadSession(): Session | null {
  const raw = sessionStorage.getItem(KEY)
  if (!raw) {
    return null
  }
  try {
    return JSON.parse(raw) as Session
  } catch {
    return null
  }
}

export function saveSession(session: Session): void {
  sessionStorage.setItem(KEY, JSON.stringify(session))
}

export function clearSession(): void {
  sessionStorage.removeItem(KEY)
}

export async function api<T>(path: string, init: RequestInit & { token?: string; schoolId?: string } = {}): Promise<T> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  if (init.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }
  if (init.token) {
    headers.set('Authorization', `Bearer ${init.token}`)
  }
  const response = await fetch(path, { ...init, headers })
  const payload = (await response.json().catch(() => ({}))) as T & { message?: string }
  if (!response.ok) {
    throw new Error(payload.message ?? `Erreur ${response.status}`)
  }
  return payload
}
