type School = {
  id: string
  name: string
  code: string
  role: string
  roles?: string[]
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
  is_student?: boolean
  schoolId?: string
}

export type Workspace = 'direction' | 'teacher' | 'parent' | 'student'

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

export function schoolRoles(school: School): string[] {
  return school.roles?.length ? school.roles : school.role ? [school.role] : []
}

export function workspacesOf(session: Session): Workspace[] {
  const roles = session.schools.flatMap(schoolRoles)
  const list: Workspace[] = []
  if (roles.some((role) => ['school_owner', 'school_admin', 'principal'].includes(role))) {
    list.push('direction')
  }
  if (roles.includes('teacher')) {
    list.push('teacher')
  }
  if (session.is_parent) {
    list.push('parent')
  }
  if (session.is_student) {
    list.push('student')
  }
  return list
}

export function defaultWorkspace(session: Session): Workspace {
  return workspacesOf(session)[0] ?? 'parent'
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

export function isNetworkError(error: unknown): boolean {
  if (error instanceof TypeError) {
    return true
  }
  const message = error instanceof Error ? error.message : ''
  return /failed to fetch|networkerror|load failed|offline/i.test(message)
}

export type TotpChallenge = {
  challenge: 'totp' | 'totp_enroll'
  challenge_id: string
  secret?: string
  otpauth_uri?: string
  demo_code?: string
}

export function isTotpChallenge(payload: Session | TotpChallenge): payload is TotpChallenge {
  return 'challenge' in payload && typeof (payload as TotpChallenge).challenge_id === 'string'
}
