type SchoolCapabilities = {
  accueil: boolean
  famille: boolean
  classe: boolean
  finance: boolean
  caisse: boolean
  kits: boolean
  indices: boolean
  appel: boolean
  vie: boolean
  notes: boolean
  titulaire: boolean
  enseigne: boolean
}

type School = {
  id: string
  name: string
  code: string
  role: string
  roles?: string[]
  titulaire?: boolean
  enseigne?: boolean
  capabilities?: Partial<SchoolCapabilities>
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
  is_guardian?: boolean
  is_student?: boolean
  is_platform_admin?: boolean
  schoolId?: string
}

export type Workspace = 'platform' | 'direction' | 'teacher' | 'parent' | 'student'

const KEY = 'fanabe.session'

const EMPTY_CAPS: SchoolCapabilities = {
  accueil: false,
  famille: false,
  classe: false,
  finance: false,
  caisse: false,
  kits: false,
  indices: false,
  appel: false,
  vie: false,
  notes: false,
  titulaire: false,
  enseigne: false,
}

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

export function currentSchool(session: Session): School | undefined {
  return session.schools.find((row) => row.id === session.schoolId) ?? session.schools[0]
}

function isOrgRole(role: string): boolean {
  return role === 'school_owner' || role === 'school_admin' || role === 'principal'
}

export function schoolCapabilities(session: Session): SchoolCapabilities {
  const school = currentSchool(session)
  if (!school) {
    return EMPTY_CAPS
  }
  if (school.capabilities) {
    return { ...EMPTY_CAPS, ...school.capabilities }
  }
  const roles = schoolRoles(school)
  const org = roles.some(isOrgRole)
  const ownerAdmin = roles.some((role) => role === 'school_owner' || role === 'school_admin')
  const accountant = roles.includes('accountant')
  const teacher = roles.includes('teacher')
  const titulaire = Boolean(school.titulaire)
  const enseigne = Boolean(school.enseigne) || titulaire

  return {
    accueil: org,
    famille: org,
    classe: org,
    finance: org || accountant,
    caisse: ownerAdmin || accountant,
    kits: org || accountant || titulaire,
    indices: org,
    appel: teacher,
    vie: teacher && titulaire,
    notes: teacher && enseigne && !titulaire,
    titulaire,
    enseigne,
  }
}

export function workspacesOf(session: Session): Workspace[] {
  const roles = session.schools.flatMap(schoolRoles)
  const list: Workspace[] = []
  if (session.is_platform_admin) {
    list.push('platform')
  }
  if (roles.some((role) => isOrgRole(role) || role === 'accountant')) {
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

const WORKSPACE_LABEL: Record<Workspace, string> = {
  platform: 'Plateforme',
  direction: 'Direction',
  teacher: 'Cours',
  parent: 'Famille',
  student: 'Élève',
}

export function workspaceLabel(session: Session, space: Workspace): string {
  if (space === 'direction') {
    const roles = schoolRoles(currentSchool(session) ?? { id: '', name: '', code: '', role: '' })
    if (roles.includes('accountant') && !roles.some(isOrgRole)) {
      return 'Caisse'
    }
  }
  return WORKSPACE_LABEL[space]
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
  const payload = (await response.json().catch(() => ({}))) as T & {
    message?: string
    errors?: Record<string, string[]>
  }
  if (!response.ok) {
    const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined
    const fallback = firstError ?? payload.message ?? `Erreur ${response.status}`
    if (response.status >= 500 || fallback === 'Server Error') {
      throw new Error('Chargement impossible. Réessayez dans un instant.')
    }
    throw new Error(fallback)
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
