import { FormEvent, useEffect, useMemo, useState } from 'react'
import { api, clearSession, loadSession, saveSession, type Session } from './session'

type PersonRow = {
  id: string
  public_id: string
  first_name: string
  last_name: string
  kind?: string
  phone_e164?: string
  email?: string
  enrollments?: Array<{ id: string; status: string; school_id: string }>
}

export default function App() {
  const [session, setSession] = useState<Session | null>(() => loadSession())
  const [view, setView] = useState<'school' | 'parent'>(() => {
    const current = loadSession()
    if (current?.schools.length) {
      return 'school'
    }
    return 'parent'
  })

  function signedIn(next: Session) {
    const schoolId = next.schoolId ?? next.schools[0]?.id
    const stored = { ...next, schoolId }
    saveSession(stored)
    setSession(stored)
    setView(stored.schools.length ? 'school' : 'parent')
  }

  function signOut() {
    clearSession()
    setSession(null)
  }

  if (!session) {
    return <LoginScreen onSuccess={signedIn} />
  }

  return (
    <div className="min-h-svh">
      <header className="border-b border-black/10 bg-white/70">
        <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-6 py-4">
          <div>
            <p className="text-xs font-semibold tracking-[0.2em] text-fanabe-leaf uppercase">FANABE</p>
            <p className="text-sm text-neutral-700">
              {session.person.first_name} {session.person.last_name}
            </p>
          </div>
          <nav className="flex flex-wrap gap-2">
            {session.schools.length > 0 ? (
              <button type="button" className={tabClass(view === 'school')} onClick={() => setView('school')}>
                École
              </button>
            ) : null}
            {session.is_parent || session.schools.length === 0 ? (
              <button type="button" className={tabClass(view === 'parent')} onClick={() => setView('parent')}>
                Famille
              </button>
            ) : null}
            <button type="button" className="rounded-lg px-3 py-2 text-sm" onClick={signOut}>
              Déconnexion
            </button>
          </nav>
        </div>
      </header>
      {view === 'school' && session.schools.length > 0 ? (
        <SchoolScreen session={session} onSchoolChange={(schoolId) => signedIn({ ...session, schoolId })} />
      ) : (
        <ParentScreen session={session} />
      )}
    </div>
  )
}

function tabClass(active: boolean): string {
  return `rounded-lg px-3 py-2 text-sm font-medium ${active ? 'bg-fanabe-leaf text-white' : 'bg-white'}`
}

function LoginScreen({ onSuccess }: { onSuccess: (session: Session) => void }) {
  const [mode, setMode] = useState<'login' | 'invite'>('login')
  const [email, setEmail] = useState('direction.antsahabe@fanabe.test')
  const [password, setPassword] = useState('password')
  const [code, setCode] = useState('')
  const [message, setMessage] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  async function onSubmit(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      const payload =
        mode === 'login'
          ? await api<Session>('/api/v1/auth/login', {
              method: 'POST',
              body: JSON.stringify({ email, password }),
            })
          : await api<Session>('/api/v1/auth/invitations/claim', {
              method: 'POST',
              body: JSON.stringify({ code, email, password }),
            })
      onSuccess(payload)
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Connexion impossible.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="mx-auto flex min-h-svh max-w-md flex-col justify-center px-6 py-12">
      <p className="text-sm font-semibold tracking-[0.2em] text-fanabe-leaf uppercase">FANABE</p>
      <h1 className="mt-2 text-3xl font-semibold tracking-tight">L&apos;école, la famille, connectées.</h1>
      <p className="mt-3 text-base leading-relaxed text-neutral-700">
        Démo phase 1 — identité portable, inscription, foyer. Pas de SMS.
      </p>
      <div className="mt-6 flex gap-2">
        <button type="button" className={tabClass(mode === 'login')} onClick={() => setMode('login')}>
          Connexion
        </button>
        <button type="button" className={tabClass(mode === 'invite')} onClick={() => setMode('invite')}>
          Code d&apos;invitation
        </button>
      </div>
      <form onSubmit={onSubmit} className="mt-6 space-y-4" aria-label="Connexion">
        {mode === 'invite' ? (
          <label className="block text-sm font-medium">
            Code imprimé
            <input
              className={inputClass}
              value={code}
              onChange={(e) => setCode(e.target.value)}
              required
              autoComplete="one-time-code"
            />
          </label>
        ) : null}
        <label className="block text-sm font-medium">
          Email
          <input
            className={inputClass}
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />
        </label>
        <label className="block text-sm font-medium">
          Mot de passe
          <input
            className={inputClass}
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
        </label>
        <button type="submit" disabled={busy} className={primaryButtonClass}>
          {busy ? 'Connexion…' : mode === 'login' ? 'Se connecter' : 'Activer mon compte'}
        </button>
      </form>
      {message ? (
        <p className="mt-4 text-sm" role="status">
          {message}
        </p>
      ) : null}
      <p className="mt-8 text-sm text-neutral-600">
        Direction Antsahabe : <code>direction.antsahabe@fanabe.test</code> / <code>password</code>
        <br />
        Parent Andry : <code>parent.andry@fanabe.test</code> / <code>password</code>
      </p>
    </main>
  )
}

const inputClass =
  'mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2.5 text-base outline-none ring-fanabe-leaf focus:ring-2'
const primaryButtonClass =
  'min-h-11 w-full rounded-lg bg-fanabe-leaf px-4 py-2.5 text-base font-semibold text-white disabled:opacity-60'

function SchoolScreen({
  session,
  onSchoolChange,
}: {
  session: Session
  onSchoolChange: (schoolId: string) => void
}) {
  const schoolId = session.schoolId ?? session.schools[0].id
  const [people, setPeople] = useState<PersonRow[]>([])
  const [yearId, setYearId] = useState<string>('')
  const [message, setMessage] = useState<string | null>(null)
  const [invitation, setInvitation] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [form, setForm] = useState({
    parent_first: 'Voahangy',
    parent_last: 'Rasoa',
    parent_phone: '0349876543',
    parent_email: '',
    student_first: 'Tiana',
    student_last: 'Rasoa',
    student_birth: '2014-06-20',
  })

  const auth = useMemo(() => ({ token: session.token }), [session.token])

  async function refresh() {
    const years = await api<{ data: Array<{ id: string; is_current: boolean; label: string }> }>(
      `/api/v1/schools/${schoolId}/years`,
      auth,
    )
    const current = years.data.find((year) => year.is_current) ?? years.data[0]
    setYearId(current?.id ?? '')
    const list = await api<{ data: PersonRow[] }>(`/api/v1/schools/${schoolId}/people`, auth)
    setPeople(list.data)
  }

  useEffect(() => {
    refresh().catch((error: Error) => setMessage(error.message))
  }, [schoolId, session.token])

  async function createFamily(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    setInvitation(null)
    try {
      const created = await api<{ invitation_code: string }>(`/api/v1/schools/${schoolId}/families`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          school_year_id: yearId,
          parent: {
            first_name: form.parent_first,
            last_name: form.parent_last,
            phone: form.parent_phone,
            email: form.parent_email || undefined,
          },
          student: {
            first_name: form.student_first,
            last_name: form.student_last,
            birth_date: form.student_birth,
          },
        }),
      })
      setInvitation(created.invitation_code)
      await refresh()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Inscription impossible.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="mx-auto grid max-w-5xl gap-8 px-6 py-8 lg:grid-cols-2">
      <section>
        <label className="block text-sm font-medium">
          Établissement
          <select
            className={inputClass}
            value={schoolId}
            onChange={(event) => onSchoolChange(event.target.value)}
          >
            {session.schools.map((school) => (
              <option key={school.id} value={school.id}>
                {school.name}
              </option>
            ))}
          </select>
        </label>
        <h2 className="mt-6 text-xl font-semibold">Nouvelle famille</h2>
        <form onSubmit={createFamily} className="mt-4 space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <input className={inputClass} value={form.parent_first} onChange={(e) => setForm({ ...form, parent_first: e.target.value })} placeholder="Prénom parent" required />
            <input className={inputClass} value={form.parent_last} onChange={(e) => setForm({ ...form, parent_last: e.target.value })} placeholder="Nom parent" required />
          </div>
          <input className={inputClass} value={form.parent_phone} onChange={(e) => setForm({ ...form, parent_phone: e.target.value })} placeholder="Téléphone" />
          <input className={inputClass} type="email" value={form.parent_email} onChange={(e) => setForm({ ...form, parent_email: e.target.value })} placeholder="Email parent (facultatif)" />
          <div className="grid grid-cols-2 gap-3">
            <input className={inputClass} value={form.student_first} onChange={(e) => setForm({ ...form, student_first: e.target.value })} placeholder="Prénom élève" required />
            <input className={inputClass} value={form.student_last} onChange={(e) => setForm({ ...form, student_last: e.target.value })} placeholder="Nom élève" required />
          </div>
          <input className={inputClass} type="date" value={form.student_birth} onChange={(e) => setForm({ ...form, student_birth: e.target.value })} required />
          <button type="submit" disabled={busy || !yearId} className={primaryButtonClass}>
            {busy ? 'Enregistrement…' : 'Inscrire'}
          </button>
        </form>
        {invitation ? (
          <p className="mt-4 rounded-lg bg-white p-3 text-sm" role="status">
            Code d&apos;invitation parent (à imprimer) : <strong>{invitation}</strong>
          </p>
        ) : null}
        {message ? (
          <p className="mt-4 text-sm" role="status">
            {message}
          </p>
        ) : null}
      </section>
      <section>
        <h2 className="text-xl font-semibold">Personnes liées</h2>
        <ul className="mt-4 space-y-2">
          {people.map((person) => (
            <li key={`${person.id}-${person.kind ?? ''}`} className="rounded-lg bg-white px-4 py-3">
              <p className="font-medium">
                {person.first_name} {person.last_name}
              </p>
              <p className="text-sm text-neutral-600">
                {person.kind} · {person.public_id}
                {person.phone_e164 ? ` · ${person.phone_e164}` : ''}
              </p>
            </li>
          ))}
        </ul>
      </section>
    </main>
  )
}

function ParentScreen({ session }: { session: Session }) {
  const [children, setChildren] = useState<PersonRow[]>([])
  const [message, setMessage] = useState<string | null>(null)

  useEffect(() => {
    api<{ data: PersonRow[] }>('/api/v1/parent/children', { token: session.token })
      .then((payload) => setChildren(payload.data))
      .catch((error: Error) => setMessage(error.message))
  }, [session.token])

  return (
    <main className="mx-auto max-w-2xl px-6 py-8">
      <h2 className="text-xl font-semibold">Mes enfants</h2>
      <p className="mt-2 text-sm text-neutral-700">Identifiant FANABE : {session.person.public_id}</p>
      {message ? <p className="mt-4 text-sm">{message}</p> : null}
      <ul className="mt-6 space-y-2">
        {children.map((child) => (
          <li key={child.id} className="rounded-lg bg-white px-4 py-3">
            <p className="font-medium">
              {child.first_name} {child.last_name}
            </p>
            <p className="text-sm text-neutral-600">{child.public_id}</p>
          </li>
        ))}
      </ul>
      {children.length === 0 && !message ? (
        <p className="mt-6 text-sm text-neutral-600">Aucun enfant n&apos;est encore rattaché à ce compte.</p>
      ) : null}
    </main>
  )
}
