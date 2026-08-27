import { useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react'
import {
  api,
  clearSession,
  defaultWorkspace,
  loadSession,
  saveSession,
  workspacesOf,
  type Session,
  type Workspace,
} from './session'

type PersonRow = {
  id: string
  public_id: string
  first_name: string
  last_name: string
  kind?: string
  phone_e164?: string
  email?: string
}

type ClassroomRow = {
  id: string
  name: string
  grade_level_id: string
  grade_level?: { name: string }
}

type EnrollmentRow = {
  id: string
  classroom_id: string | null
  status: string
  person: { id: string; public_id: string; first_name: string; last_name: string } | null
  classroom: { id: string; name: string } | null
  invoice: { id: string; number: string; remaining_amount: number; status: string } | null
}

type RosterStudent = {
  enrollment_id: string
  person: { id: string; public_id: string; first_name: string; last_name: string } | null
}

type InvoiceRow = {
  id: string
  number: string
  net_amount: number
  paid_amount: number
  remaining_amount: number
  status: string
  discount_amount: number
  installments: Array<{
    id: string
    due_on: string
    amount: number
    paid_amount: number
    remaining_amount: number
    status: string
  }>
}

type CockpitAction = {
  id: string
  enrollment_id: string
  template_key: string
  title: string
  reason_summary: string
  priority: string
  status: string
  student: { id: string; first_name: string; last_name: string } | null
}

type Cockpit = {
  as_of: string
  attendance: { present: number; absent: number }
  collected_today: number
  outstanding_amount: number
  risk_counts: { low: number; medium: number; high: number; critical: number }
  forecast: {
    week_starting_on: string
    expected_amount: number
    confidence_low_amount: number
    confidence_high_amount: number
  } | null
  actions: CockpitAction[]
}

type ParentInboxMessage = {
  id: string
  channel: string
  subject: string
  body: string
  queued_at: string | null
  sent_at: string | null
}

type ChildFinance = {
  remaining_amount: number
  data: Array<{
    school: { name: string } | null
    classroom: { name: string } | null
    invoice: InvoiceRow | null
    payments: Array<{ amount: number; received_on: string; receipt_number: string | null }>
  }>
}

type StudentOverview = {
  person: PersonRow
  enrollment: {
    student_number: string | null
    school: { name: string } | null
    classroom: { name: string } | null
  } | null
  attendance: Array<{ id: string; date: string; status: string }>
  finance: {
    remaining_amount: number
    invoice: InvoiceRow | null
    payments: Array<{ amount: number; received_on: string; receipt_number: string | null }>
  }
}

type DirectionTab = 'accueil' | 'famille' | 'classe' | 'caisse'
type TeacherTab = 'classe' | 'appel'

const PAGE_SIZE = 40

const DIRECTION_NAV: Array<{ id: DirectionTab; label: string }> = [
  { id: 'accueil', label: 'Aujourd’hui' },
  { id: 'famille', label: 'Familles' },
  { id: 'classe', label: 'Classes' },
  { id: 'caisse', label: 'Caisse' },
]

const TEACHER_NAV: Array<{ id: TeacherTab; label: string }> = [
  { id: 'appel', label: 'Appel' },
  { id: 'classe', label: 'Effectif' },
]

const WORKSPACE_LABEL: Record<Workspace, string> = {
  direction: 'Direction',
  teacher: 'Classe',
  parent: 'Famille',
  student: 'Élève',
}

export default function App() {
  const [session, setSession] = useState<Session | null>(() => loadSession())
  const [workspace, setWorkspace] = useState<Workspace>(() => {
    const current = loadSession()
    return current ? defaultWorkspace(current) : 'parent'
  })
  const [directionTab, setDirectionTab] = useState<DirectionTab>('accueil')
  const [teacherTab, setTeacherTab] = useState<TeacherTab>('appel')

  function signedIn(next: Session) {
    const schoolId = next.schoolId ?? next.schools[0]?.id
    const stored = { ...next, schoolId }
    saveSession(stored)
    setSession(stored)
    setWorkspace(defaultWorkspace(stored))
  }

  function signOut() {
    clearSession()
    setSession(null)
  }

  if (!session) {
    return <LoginScreen onSuccess={signedIn} />
  }

  const schoolName = session.schools.find((row) => row.id === session.schoolId)?.name ?? session.schools[0]?.name
  const spaces = workspacesOf(session)

  return (
    <div className="min-h-svh">
      <header className="sticky top-0 z-20 border-b border-black/10 bg-fanabe-ink text-white">
        <div className="flex h-11 items-center gap-2 px-3">
          <Logo />
          {schoolName && (workspace === 'direction' || workspace === 'teacher') ? (
            <span className="hidden max-w-40 truncate text-xs text-white/55 sm:inline">{schoolName}</span>
          ) : null}
          {spaces.length > 1 ? (
            <nav className="ml-1 flex min-w-0 items-center gap-0.5" aria-label="Espaces">
              {spaces.map((space) => (
                <button
                  key={space}
                  type="button"
                  className={chromeTab(workspace === space)}
                  onClick={() => setWorkspace(space)}
                >
                  {WORKSPACE_LABEL[space]}
                </button>
              ))}
            </nav>
          ) : (
            <span className="text-xs font-medium text-white/70">{WORKSPACE_LABEL[workspace]}</span>
          )}
          <div className="ml-auto flex min-w-0 items-center gap-2">
            {session.schools.length > 1 && (workspace === 'direction' || workspace === 'teacher') ? (
              <select
                className="h-7 max-w-36 truncate rounded-md border-0 bg-white/10 px-2 text-xs text-white"
                value={session.schoolId ?? session.schools[0].id}
                onChange={(event) => signedIn({ ...session, schoolId: event.target.value })}
              >
                {session.schools.map((school) => (
                  <option key={school.id} value={school.id} className="text-fanabe-ink">
                    {school.name}
                  </option>
                ))}
              </select>
            ) : null}
            <span className="hidden truncate text-xs text-white/55 sm:inline">
              {session.person.first_name} {session.person.last_name}
            </span>
            <button type="button" className="h-7 rounded-md px-2 text-xs text-white/70 hover:bg-white/10" onClick={signOut}>
              Sortir
            </button>
          </div>
        </div>
        {workspace === 'direction' ? (
          <nav className="flex h-9 items-center gap-1 overflow-x-auto border-t border-white/10 px-3" aria-label="Direction">
            {DIRECTION_NAV.map((item) => (
              <button key={item.id} type="button" className={chromeTab(directionTab === item.id)} onClick={() => setDirectionTab(item.id)}>
                {item.label}
              </button>
            ))}
          </nav>
        ) : null}
        {workspace === 'teacher' ? (
          <nav className="flex h-9 items-center gap-1 overflow-x-auto border-t border-white/10 px-3" aria-label="Classe">
            {TEACHER_NAV.map((item) => (
              <button key={item.id} type="button" className={chromeTab(teacherTab === item.id)} onClick={() => setTeacherTab(item.id)}>
                {item.label}
              </button>
            ))}
          </nav>
        ) : null}
      </header>
      {workspace === 'direction' ? (
        <DirectionScreen session={session} tab={directionTab} onTab={setDirectionTab} />
      ) : workspace === 'teacher' ? (
        <TeacherScreen session={session} tab={teacherTab} />
      ) : workspace === 'student' ? (
        <StudentScreen session={session} />
      ) : (
        <ParentScreen session={session} />
      )}
    </div>
  )
}

function Logo() {
  return (
    <div className="flex shrink-0 items-center gap-1.5">
      <span className="grid h-6 w-6 place-items-center rounded-md bg-fanabe-leaf">
        <svg viewBox="0 0 24 24" className="h-3.5 w-3.5 fill-white" aria-hidden>
          <path d="M12 3c.5 3.4 2.8 5.8 6.5 6.6C18.2 16.2 14.4 19.5 12 20.8 9.6 19.5 5.8 16.2 5.5 9.6 9.2 8.8 11.5 6.4 12 3z" />
        </svg>
      </span>
      <span className="text-sm font-semibold tracking-tight">FANABE</span>
    </div>
  )
}

function chromeTab(active: boolean): string {
  return `h-7 shrink-0 rounded-md px-2.5 text-xs font-medium ${
    active ? 'bg-white text-fanabe-ink' : 'text-white/70 hover:bg-white/10'
  }`
}

function formatAr(amount: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(amount)} Ar`
}

function formatDate(value: string): string {
  return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
  })
}

function kindLabel(kind?: string): string {
  if (kind === 'student') return 'Élève'
  if (kind === 'parent') return 'Parent'
  if (kind === 'staff') return 'Personnel'
  return kind ?? 'Personne'
}

function invoiceLabel(status?: string): string {
  if (status === 'paid') return 'Soldée'
  if (status === 'partially_paid') return 'Partiel'
  if (status === 'issued') return 'À payer'
  return status ?? ''
}

function attendanceLabel(status?: string): string {
  if (status === 'present') return 'Présent'
  if (status === 'absent') return 'Absent'
  if (status === 'late') return 'Retard'
  if (status === 'excused') return 'Excusé'
  return status ?? ''
}

function Banner({ message, onClear }: { message: string; onClear: () => void }) {
  const error = /impossible|invalide|erreur|appartient pas/i.test(message)
  return (
    <p
      role="status"
      className={`mb-3 flex items-center justify-between gap-3 rounded-md px-3 py-2 text-sm ${
        error ? 'bg-red-50 text-red-900' : 'bg-fanabe-mist text-fanabe-leaf-dark'
      }`}
    >
      <span>{message}</span>
      <button type="button" className="h-6 w-6 text-base" onClick={onClear} aria-label="Fermer">
        ×
      </button>
    </p>
  )
}

async function copyText(value: string): Promise<void> {
  await navigator.clipboard.writeText(value)
}

function matchesQuery(haystack: string, query: string): boolean {
  const needle = query.trim().toLowerCase()
  return needle === '' || haystack.toLowerCase().includes(needle)
}

function pageOf<T>(rows: T[], page: number): T[] {
  const start = (page - 1) * PAGE_SIZE
  return rows.slice(start, start + PAGE_SIZE)
}

function pageCount(total: number): number {
  return Math.max(1, Math.ceil(total / PAGE_SIZE))
}

const inputClass =
  'h-8 w-full rounded-md border border-black/10 bg-white px-2.5 text-sm outline-none ring-fanabe-leaf focus:ring-2'
const btnPrimary =
  'inline-flex h-8 items-center justify-center rounded-md bg-fanabe-leaf px-3 text-sm font-semibold text-white hover:bg-fanabe-leaf-dark disabled:opacity-50'
const btnBlock = `${btnPrimary} w-full`
const btnGhost =
  'inline-flex h-8 items-center justify-center rounded-md border border-black/10 bg-white px-3 text-sm font-medium hover:bg-black/5 disabled:opacity-50'

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="block text-xs font-medium text-neutral-600">
      {label}
      {children}
    </label>
  )
}

function Panel({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <section className={`rounded-lg border border-black/8 bg-fanabe-paper ${className}`}>{children}</section>
}

function Pager({ page, total, onPage }: { page: number; total: number; onPage: (page: number) => void }) {
  const pages = pageCount(total)
  if (total <= PAGE_SIZE) {
    return <p className="text-xs text-neutral-500">{total} résultat{total > 1 ? 's' : ''}</p>
  }
  return (
    <div className="flex items-center justify-between gap-2 text-xs text-neutral-600">
      <span>
        {total} · page {page}/{pages}
      </span>
      <div className="flex gap-1">
        <button type="button" className={btnGhost} disabled={page <= 1} onClick={() => onPage(page - 1)}>
          Préc.
        </button>
        <button type="button" className={btnGhost} disabled={page >= pages} onClick={() => onPage(page + 1)}>
          Suiv.
        </button>
      </div>
    </div>
  )
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

  const demos = [
    { label: 'Direction', email: 'direction.antsahabe@fanabe.test' },
    { label: 'Professeur', email: 'teacher.antsahabe@fanabe.test' },
    { label: 'Parent', email: 'parent.andry@fanabe.test' },
    { label: 'Élève', email: 'eleve.fanja@fanabe.test' },
  ]

  return (
    <main className="grid min-h-svh lg:grid-cols-[20rem_1fr]">
      <section className="hidden flex-col justify-between bg-fanabe-ink px-8 py-8 text-white lg:flex">
        <Logo />
        <div>
          <p className="font-display text-3xl leading-tight">L’école, la famille, connectées.</p>
          <p className="mt-3 text-sm leading-relaxed text-white/65">
            Direction, classe, famille, élève — sans SMS, sans encaissement en ligne.
          </p>
        </div>
        <p className="text-xs text-white/40">FANABE · Madagascar</p>
      </section>
      <section className="flex flex-col justify-center px-5 py-10 sm:px-12">
        <div className="lg:hidden">
          <Logo />
        </div>
        <h1 className="mt-6 text-xl font-semibold lg:mt-0">Connexion</h1>
        <div className="mt-4 grid grid-cols-2 gap-1 rounded-md bg-black/5 p-0.5">
          <button type="button" className={modeTab(mode === 'login')} onClick={() => setMode('login')}>
            Compte
          </button>
          <button type="button" className={modeTab(mode === 'invite')} onClick={() => setMode('invite')}>
            Invitation
          </button>
        </div>
        <form onSubmit={onSubmit} className="mt-4 max-w-sm space-y-3" aria-label="Connexion">
          {mode === 'invite' ? (
            <Field label="Code imprimé">
              <input className={inputClass} value={code} onChange={(e) => setCode(e.target.value)} required autoComplete="one-time-code" />
            </Field>
          ) : null}
          <Field label="Email">
            <input className={inputClass} type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </Field>
          <Field label="Mot de passe">
            <input className={inputClass} type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          </Field>
          <button type="submit" disabled={busy} className={btnBlock}>
            {busy ? 'Connexion…' : mode === 'login' ? 'Entrer' : 'Activer le compte'}
          </button>
        </form>
        {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}
        <div className="mt-6 max-w-sm">
          <p className="text-[10px] font-semibold uppercase tracking-wider text-neutral-500">Démo</p>
          <div className="mt-1.5 flex flex-wrap gap-1">
            {demos.map((demo) => (
              <button
                key={demo.email}
                type="button"
                className="h-7 rounded-md bg-white px-2 text-xs shadow-sm"
                onClick={() => {
                  setMode('login')
                  setEmail(demo.email)
                  setPassword('password')
                }}
              >
                {demo.label}
              </button>
            ))}
          </div>
        </div>
      </section>
    </main>
  )
}

function modeTab(active: boolean): string {
  return `h-8 rounded-md text-sm font-medium ${active ? 'bg-white shadow-sm' : 'text-neutral-600'}`
}

function DirectionScreen({
  session,
  tab,
  onTab,
}: {
  session: Session
  tab: DirectionTab
  onTab: (tab: DirectionTab) => void
}) {
  const schoolId = session.schoolId ?? session.schools[0].id
  const [people, setPeople] = useState<PersonRow[]>([])
  const [yearId, setYearId] = useState('')
  const [yearLabel, setYearLabel] = useState('2026-2027')
  const [classrooms, setClassrooms] = useState<ClassroomRow[]>([])
  const [grades, setGrades] = useState<Array<{ id: string; name: string }>>([])
  const [enrollments, setEnrollments] = useState<EnrollmentRow[]>([])
  const [message, setMessage] = useState<string | null>(null)
  const [invitation, setInvitation] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [query, setQuery] = useState('')
  const [page, setPage] = useState(1)
  const [classFilter, setClassFilter] = useState('')
  const [form, setForm] = useState({
    parent_first: 'Voahangy',
    parent_last: 'Rasoa',
    parent_phone: '0349876543',
    parent_email: '',
    student_first: 'Tiana',
    student_last: 'Rasoa',
    student_birth: '2014-06-20',
  })
  const [newClassName, setNewClassName] = useState('6ème B')
  const [newClassGrade, setNewClassGrade] = useState('')
  const [selectedEnrollment, setSelectedEnrollment] = useState('')
  const [invoice, setInvoice] = useState<InvoiceRow | null>(null)
  const [paymentAmount, setPaymentAmount] = useState('50000')
  const [paymentMethod, setPaymentMethod] = useState('cash')
  const [receipt, setReceipt] = useState<string | null>(null)
  const [cockpit, setCockpit] = useState<Cockpit | null>(null)

  const auth = useMemo(() => ({ token: session.token }), [session.token])
  const activeEnrollments = enrollments.filter((row) => row.status === 'active')
  const filteredPeople = people.filter((person) =>
    matchesQuery(`${person.first_name} ${person.last_name} ${person.public_id}`, query),
  )
  const filteredEnrollments = activeEnrollments.filter((row) => {
    const text = `${row.person?.first_name ?? ''} ${row.person?.last_name ?? ''} ${row.person?.public_id ?? ''}`
    return matchesQuery(text, query) && (classFilter === '' || row.classroom_id === classFilter)
  })
  const pagedPeople = pageOf(filteredPeople, page)
  const pagedEnrollments = pageOf(filteredEnrollments, page)
  const selectedStudent = activeEnrollments.find((row) => row.id === selectedEnrollment)

  async function loadCore() {
    const years = await api<{ data: Array<{ id: string; is_current: boolean; label: string }> }>(
      `/api/v1/schools/${schoolId}/years`,
      auth,
    )
    const current = years.data.find((year) => year.is_current) ?? years.data[0]
    setYearId(current?.id ?? '')
    setYearLabel(current?.label ?? '2026-2027')
    const [classList, gradeList, today] = await Promise.all([
      api<{ data: ClassroomRow[] }>(`/api/v1/schools/${schoolId}/classrooms`, auth),
      api<{ data: Array<{ id: string; name: string }> }>(`/api/v1/schools/${schoolId}/grade-levels`, auth),
      api<Cockpit>(`/api/v1/schools/${schoolId}/cockpit`, auth),
    ])
    setClassrooms(classList.data)
    setGrades(gradeList.data)
    setCockpit(today)
    setNewClassGrade((prev) => prev || gradeList.data[0]?.id || '')
  }

  async function loadPeople() {
    const list = await api<{ data: PersonRow[] }>(`/api/v1/schools/${schoolId}/people`, auth)
    setPeople(list.data)
  }

  async function loadEnrollments() {
    const enrollmentList = await api<{ data: EnrollmentRow[] }>(`/api/v1/schools/${schoolId}/enrollments`, auth)
    setEnrollments(enrollmentList.data)
    const active = enrollmentList.data.find((row) => row.status === 'active')
    setSelectedEnrollment((prev) => prev || active?.id || '')
  }

  useEffect(() => {
    loadCore().catch((error: Error) => setMessage(error.message))
  }, [schoolId, session.token])

  useEffect(() => {
    if (tab === 'famille') {
      loadPeople().catch((error: Error) => setMessage(error.message))
    }
    if (tab === 'classe' || tab === 'caisse') {
      loadEnrollments().catch((error: Error) => setMessage(error.message))
    }
    setQuery('')
    setPage(1)
  }, [tab, schoolId, session.token])

  useEffect(() => {
    setPage(1)
  }, [query, classFilter])

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
      setMessage('Famille inscrite. Remettez le code d’invitation au parent.')
      await Promise.all([loadPeople(), loadEnrollments(), loadCore()])
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Inscription impossible.')
    } finally {
      setBusy(false)
    }
  }

  async function createClassroom(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/classrooms`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({ school_year_id: yearId, grade_level_id: newClassGrade, name: newClassName }),
      })
      setMessage(`Classe ${newClassName} créée.`)
      await loadCore()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Classe impossible à créer.')
    } finally {
      setBusy(false)
    }
  }

  async function assignClassroom(enrollmentId: string, classroomId: string) {
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/enrollments/${enrollmentId}/assign-classroom`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({ classroom_id: classroomId }),
      })
      await loadEnrollments()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Affectation impossible.')
    } finally {
      setBusy(false)
    }
  }

  async function loadInvoice(enrollmentId: string) {
    try {
      const payload = await api<{ data: InvoiceRow }>(`/api/v1/schools/${schoolId}/enrollments/${enrollmentId}/invoice`, auth)
      setInvoice(payload.data)
    } catch {
      setInvoice(null)
    }
  }

  useEffect(() => {
    if (!selectedEnrollment || tab !== 'caisse') return
    loadInvoice(selectedEnrollment).catch(() => setInvoice(null))
  }, [selectedEnrollment, tab, schoolId, session.token])

  async function generateInvoice() {
    setBusy(true)
    setMessage(null)
    setReceipt(null)
    try {
      const payload = await api<{ data: InvoiceRow }>(`/api/v1/schools/${schoolId}/enrollments/${selectedEnrollment}/invoices`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({}),
      })
      setInvoice(payload.data)
      setMessage(`Facture ${payload.data.number} émise.`)
      await Promise.all([loadEnrollments(), loadCore()])
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Facture impossible à générer.')
    } finally {
      setBusy(false)
    }
  }

  async function recordPayment(event: FormEvent) {
    event.preventDefault()
    if (!invoice) return
    setBusy(true)
    setMessage(null)
    try {
      const payload = await api<{ data: { receipt: { number: string } }; invoice: InvoiceRow }>(
        `/api/v1/schools/${schoolId}/payments`,
        {
          ...auth,
          method: 'POST',
          body: JSON.stringify({
            invoice_id: invoice.id,
            amount: Number(paymentAmount),
            method: paymentMethod,
            received_on: new Date().toISOString().slice(0, 10),
            idempotency_key: crypto.randomUUID(),
          }),
        },
      )
      setReceipt(payload.data.receipt.number)
      setInvoice(payload.invoice)
      setMessage(`Paiement enregistré. Reçu ${payload.data.receipt.number}.`)
      await Promise.all([loadEnrollments(), loadCore()])
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Paiement impossible à enregistrer.')
    } finally {
      setBusy(false)
    }
  }

  async function relance(taskId: string) {
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/collection/tasks/${taskId}/relance`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({}),
      })
      setMessage('Relance enregistrée. Avis imprimé et message envoyé à la famille.')
      await loadCore()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Relance impossible.')
    } finally {
      setBusy(false)
    }
  }

  async function downloadCsv() {
    const response = await fetch(`/api/v1/schools/${schoolId}/payments/export`, {
      headers: { Authorization: `Bearer ${session.token}`, Accept: 'text/csv' },
    })
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'paiements-fanabe.csv'
    link.click()
    URL.revokeObjectURL(url)
  }

  return (
    <main className="px-3 py-3 sm:px-4">
      {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}

      {tab === 'accueil' ? (
        <div className="space-y-3">
          <div className="grid grid-cols-2 overflow-hidden rounded-lg border border-black/8 bg-black/[0.04] lg:grid-cols-4">
            <Kpi label="Présents" value={String(cockpit?.attendance.present ?? 0)} hint={`${cockpit?.attendance.absent ?? 0} abs.`} />
            <Kpi label="Encaissé" value={formatAr(cockpit?.collected_today ?? 0)} hint="aujourd’hui" />
            <Kpi label="Reste dû" value={formatAr(cockpit?.outstanding_amount ?? 0)} hint={yearLabel} />
            <Kpi label="À relancer" value={String(cockpit?.actions.length ?? 0)} hint="faits, pas un score" />
          </div>
          <Panel>
            <div className="flex items-center justify-between gap-3 border-b border-black/5 px-3 py-2">
              <h2 className="text-sm font-semibold">Actions du jour</h2>
              {cockpit?.forecast ? (
                <p className="text-xs text-neutral-500">
                  Semaine {formatAr(cockpit.forecast.expected_amount)}
                  <span className="hidden sm:inline">
                    {' '}
                    ({formatAr(cockpit.forecast.confidence_low_amount)}–{formatAr(cockpit.forecast.confidence_high_amount)})
                  </span>
                </p>
              ) : null}
            </div>
            <table className="w-full text-sm">
              <tbody>
                {(cockpit?.actions ?? []).map((row) => (
                  <tr key={row.id} className="border-t border-black/5 first:border-t-0">
                    <td className="px-3 py-2">
                      <p className="font-medium">
                        {row.student ? `${row.student.first_name} ${row.student.last_name}` : row.title}
                      </p>
                      <p className="text-xs text-neutral-600">{row.reason_summary}</p>
                    </td>
                    <td className="w-24 px-3 py-2 text-right">
                      <button type="button" disabled={busy || row.status === 'resolved'} className={btnPrimary} onClick={() => relance(row.id)}>
                        Relancer
                      </button>
                    </td>
                  </tr>
                ))}
                {(cockpit?.actions.length ?? 0) === 0 ? (
                  <tr>
                    <td className="px-3 py-4 text-sm text-neutral-600" colSpan={2}>
                      Rien à relancer pour le moment.
                    </td>
                  </tr>
                ) : null}
              </tbody>
            </table>
          </Panel>
          <div className="flex flex-wrap gap-2 text-xs">
            <button type="button" className={btnGhost} onClick={() => onTab('famille')}>
              Inscrire une famille
            </button>
            <button type="button" className={btnGhost} onClick={() => onTab('classe')}>
              Classes
            </button>
            <button type="button" className={btnGhost} onClick={() => onTab('caisse')}>
              Enregistrer un paiement
            </button>
          </div>
        </div>
      ) : null}

      {tab === 'famille' ? (
        <div className="grid gap-3 lg:grid-cols-[18rem_1fr]">
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">Nouvelle famille</h2>
            <form onSubmit={createFamily} className="mt-3 space-y-2">
              <div className="grid grid-cols-2 gap-2">
                <input className={inputClass} value={form.parent_first} onChange={(e) => setForm({ ...form, parent_first: e.target.value })} placeholder="Prénom parent" required />
                <input className={inputClass} value={form.parent_last} onChange={(e) => setForm({ ...form, parent_last: e.target.value })} placeholder="Nom parent" required />
              </div>
              <input className={inputClass} value={form.parent_phone} onChange={(e) => setForm({ ...form, parent_phone: e.target.value })} placeholder="Téléphone" />
              <input className={inputClass} type="email" value={form.parent_email} onChange={(e) => setForm({ ...form, parent_email: e.target.value })} placeholder="Email (facultatif)" />
              <div className="grid grid-cols-2 gap-2">
                <input className={inputClass} value={form.student_first} onChange={(e) => setForm({ ...form, student_first: e.target.value })} placeholder="Prénom élève" required />
                <input className={inputClass} value={form.student_last} onChange={(e) => setForm({ ...form, student_last: e.target.value })} placeholder="Nom élève" required />
              </div>
              <input className={inputClass} type="date" value={form.student_birth} onChange={(e) => setForm({ ...form, student_birth: e.target.value })} required />
              <button type="submit" disabled={busy || !yearId} className={btnBlock}>
                {busy ? 'Enregistrement…' : 'Inscrire'}
              </button>
            </form>
            {invitation ? (
              <div className="mt-3 flex items-center justify-between gap-2 rounded-md bg-fanabe-mist px-2 py-2 text-xs">
                <strong className="font-mono">{invitation}</strong>
                <button type="button" className={btnGhost} onClick={() => copyText(invitation)}>
                  Copier
                </button>
              </div>
            ) : null}
          </Panel>
          <Panel className="min-w-0">
            <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
              <input className={`${inputClass} max-w-xs`} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Nom ou identifiant" />
              <Pager page={page} total={filteredPeople.length} onPage={setPage} />
            </div>
            <div className="overflow-auto">
              <table className="w-full text-sm">
                <thead className="bg-black/[0.03] text-left text-[11px] uppercase tracking-wide text-neutral-500">
                  <tr>
                    <th className="px-3 py-1.5 font-medium">Nom</th>
                    <th className="px-3 py-1.5 font-medium">Rôle</th>
                    <th className="px-3 py-1.5 font-medium">Identifiant</th>
                  </tr>
                </thead>
                <tbody>
                  {pagedPeople.map((person) => (
                    <tr key={`${person.id}-${person.kind ?? ''}`} className="border-t border-black/5">
                      <td className="px-3 py-1.5 font-medium">
                        {person.first_name} {person.last_name}
                      </td>
                      <td className="px-3 py-1.5 text-neutral-600">{kindLabel(person.kind)}</td>
                      <td className="px-3 py-1.5 font-mono text-xs text-neutral-600">{person.public_id}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Panel>
        </div>
      ) : null}

      {tab === 'classe' ? (
        <div className="grid gap-3 lg:grid-cols-[16rem_1fr]">
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">Classes · {yearLabel}</h2>
            <form onSubmit={createClassroom} className="mt-3 space-y-2">
              <select className={inputClass} value={newClassGrade} onChange={(e) => setNewClassGrade(e.target.value)}>
                {grades.map((grade) => (
                  <option key={grade.id} value={grade.id}>
                    {grade.name}
                  </option>
                ))}
              </select>
              <input className={inputClass} value={newClassName} onChange={(e) => setNewClassName(e.target.value)} required />
              <button type="submit" disabled={busy || !yearId || !newClassGrade} className={btnBlock}>
                Créer
              </button>
            </form>
            <ul className="mt-3 divide-y divide-black/5 text-sm">
              {classrooms.map((classroom) => {
                const count = activeEnrollments.filter((row) => row.classroom_id === classroom.id).length
                return (
                  <li key={classroom.id}>
                    <button
                      type="button"
                      className={`flex w-full items-center justify-between py-1.5 text-left ${classFilter === classroom.id ? 'font-semibold text-fanabe-leaf' : ''}`}
                      onClick={() => setClassFilter((prev) => (prev === classroom.id ? '' : classroom.id))}
                    >
                      <span>{classroom.name}</span>
                      <span className="text-xs text-neutral-500">{count}</span>
                    </button>
                  </li>
                )
              })}
            </ul>
          </Panel>
          <Panel className="min-w-0">
            <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
              <input className={`${inputClass} max-w-xs`} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Rechercher un élève" />
              <Pager page={page} total={filteredEnrollments.length} onPage={setPage} />
            </div>
            <div className="overflow-auto">
              <table className="w-full text-sm">
                <thead className="bg-black/[0.03] text-left text-[11px] uppercase tracking-wide text-neutral-500">
                  <tr>
                    <th className="px-3 py-1.5 font-medium">Élève</th>
                    <th className="px-3 py-1.5 font-medium">Classe</th>
                  </tr>
                </thead>
                <tbody>
                  {pagedEnrollments.map((row) => (
                    <tr key={row.id} className="border-t border-black/5">
                      <td className="px-3 py-1.5 font-medium">
                        {row.person?.first_name} {row.person?.last_name}
                      </td>
                      <td className="px-3 py-1.5">
                        <select
                          className={inputClass}
                          value={row.classroom_id ?? ''}
                          onChange={(event) => {
                            if (event.target.value) void assignClassroom(row.id, event.target.value)
                          }}
                        >
                          <option value="">Sans classe</option>
                          {classrooms.map((classroom) => (
                            <option key={classroom.id} value={classroom.id}>
                              {classroom.name}
                            </option>
                          ))}
                        </select>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Panel>
        </div>
      ) : null}

      {tab === 'caisse' ? (
        <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_20rem]">
          <Panel className="min-w-0">
            <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
              <input className={`${inputClass} max-w-[12rem]`} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Nom ou classe" />
              <select className={`${inputClass} w-auto`} value={classFilter} onChange={(e) => setClassFilter(e.target.value)}>
                <option value="">Toutes les classes</option>
                {classrooms.map((classroom) => (
                  <option key={classroom.id} value={classroom.id}>
                    {classroom.name}
                  </option>
                ))}
              </select>
              <button type="button" className={`${btnGhost} ml-auto`} onClick={() => downloadCsv().catch((error: Error) => setMessage(error.message))}>
                CSV
              </button>
              <Pager page={page} total={filteredEnrollments.length} onPage={setPage} />
            </div>
            <div className="overflow-auto">
              <table className="w-full text-sm">
                <thead className="bg-black/[0.03] text-left text-[11px] uppercase tracking-wide text-neutral-500">
                  <tr>
                    <th className="px-3 py-1.5 font-medium">Élève</th>
                    <th className="px-3 py-1.5 font-medium">Classe</th>
                    <th className="px-3 py-1.5 text-right font-medium">Reste</th>
                  </tr>
                </thead>
                <tbody>
                  {pagedEnrollments.map((row) => {
                    const selected = row.id === selectedEnrollment
                    return (
                      <tr key={row.id} className={`border-t border-black/5 ${selected ? 'bg-fanabe-mist' : 'hover:bg-black/[0.03]'}`}>
                        <td className="px-3 py-1">
                          <button type="button" className="w-full text-left font-medium" onClick={() => setSelectedEnrollment(row.id)}>
                            {row.person?.first_name} {row.person?.last_name}
                          </button>
                        </td>
                        <td className="px-3 py-1 text-neutral-600">{row.classroom?.name ?? '—'}</td>
                        <td className="px-3 py-1 text-right tabular-nums">
                          {row.invoice ? formatAr(row.invoice.remaining_amount) : '—'}
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </Panel>
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">
              {selectedStudent ? `${selectedStudent.person?.first_name} ${selectedStudent.person?.last_name}` : 'Caisse'}
            </h2>
            <p className="mt-1 text-xs text-neutral-500">Enregistrement hors ligne — FANABE n’encaisse pas.</p>
            <button type="button" disabled={busy || !selectedEnrollment} className={`${btnGhost} mt-3`} onClick={generateInvoice}>
              Générer la facture
            </button>
            {invoice ? (
              <div className="mt-3 space-y-2 text-sm">
                <div className="flex items-center justify-between">
                  <span className="font-medium">{invoice.number}</span>
                  <span className="text-xs text-neutral-500">{invoiceLabel(invoice.status)}</span>
                </div>
                <div className="h-1.5 overflow-hidden rounded-full bg-black/10">
                  <div
                    className="h-full bg-fanabe-leaf"
                    style={{ width: `${invoice.net_amount === 0 ? 0 : Math.min(100, (invoice.paid_amount / invoice.net_amount) * 100)}%` }}
                  />
                </div>
                <p className="text-xs">
                  {formatAr(invoice.paid_amount)} / {formatAr(invoice.net_amount)} · reste <strong>{formatAr(invoice.remaining_amount)}</strong>
                </p>
                <ul className="space-y-1 text-xs text-neutral-600">
                  {invoice.installments.map((row) => (
                    <li key={row.id} className="flex justify-between gap-2">
                      <span>{formatDate(row.due_on)}</span>
                      <span>{formatAr(row.remaining_amount)}</span>
                    </li>
                  ))}
                </ul>
              </div>
            ) : (
              <p className="mt-3 text-xs text-neutral-500">Pas encore de facture.</p>
            )}
            <form onSubmit={recordPayment} className="mt-3 space-y-2">
              <Field label="Montant (Ar)">
                <input className={inputClass} type="number" min={1} step={1} value={paymentAmount} onChange={(e) => setPaymentAmount(e.target.value)} required />
              </Field>
              <select className={inputClass} value={paymentMethod} onChange={(e) => setPaymentMethod(e.target.value)}>
                <option value="cash">Espèces</option>
                <option value="mobile_money">Mobile money</option>
                <option value="bank_transfer">Virement</option>
              </select>
              <button type="submit" disabled={busy || !invoice} className={btnBlock}>
                {busy ? 'Enregistrement…' : 'Enregistrer'}
              </button>
            </form>
            {receipt ? (
              <div className="mt-2 flex items-center justify-between gap-2 text-xs">
                <span>
                  Reçu <strong>{receipt}</strong>
                </span>
                <button type="button" className={btnGhost} onClick={() => copyText(receipt)}>
                  Copier
                </button>
              </div>
            ) : null}
          </Panel>
        </div>
      ) : null}
    </main>
  )
}

function Kpi({ label, value, hint }: { label: string; value: string; hint: string }) {
  return (
    <div className="bg-fanabe-paper px-3 py-2">
      <p className="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">{label}</p>
      <p className="text-lg font-semibold leading-tight">{value}</p>
      <p className="text-[11px] text-neutral-500">{hint}</p>
    </div>
  )
}

function TeacherScreen({ session, tab }: { session: Session; tab: TeacherTab }) {
  const schoolId = session.schoolId ?? session.schools[0].id
  const [classrooms, setClassrooms] = useState<ClassroomRow[]>([])
  const [selectedClassroom, setSelectedClassroom] = useState('')
  const [students, setStudents] = useState<RosterStudent[]>([])
  const [query, setQuery] = useState('')
  const [attendanceDate, setAttendanceDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [marks, setMarks] = useState<Record<string, string>>({})
  const [message, setMessage] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const auth = useMemo(() => ({ token: session.token }), [session.token])
  const currentClass = classrooms.find((row) => row.id === selectedClassroom)
  const visibleStudents = students.filter((row) =>
    matchesQuery(`${row.person?.first_name ?? ''} ${row.person?.last_name ?? ''} ${row.person?.public_id ?? ''}`, query),
  )

  async function refresh() {
    const classList = await api<{ data: ClassroomRow[] }>(`/api/v1/schools/${schoolId}/classrooms`, auth)
    setClassrooms(classList.data)
    setSelectedClassroom((prev) => prev || classList.data[0]?.id || '')
  }

  async function loadRoster(classroomId: string) {
    const payload = await api<{ data: { students: RosterStudent[] } }>(
      `/api/v1/schools/${schoolId}/classrooms/${classroomId}/roster`,
      auth,
    )
    setStudents(payload.data.students)
  }

  async function loadAttendance(classroomId: string, date: string) {
    const payload = await api<{
      data: Array<{ enrollment_id: string; attendance: { status: string } | null }>
    }>(`/api/v1/schools/${schoolId}/attendance?classroom_id=${classroomId}&date=${date}&session=full_day`, auth)
    const next: Record<string, string> = {}
    for (const row of payload.data) {
      next[row.enrollment_id] = row.attendance?.status ?? 'present'
    }
    setMarks(next)
  }

  useEffect(() => {
    refresh().catch((error: Error) => setMessage(error.message))
  }, [schoolId, session.token])

  useEffect(() => {
    if (!selectedClassroom) return
    loadRoster(selectedClassroom).catch((error: Error) => setMessage(error.message))
  }, [selectedClassroom, schoolId, session.token])

  useEffect(() => {
    if (!selectedClassroom || tab !== 'appel') return
    loadAttendance(selectedClassroom, attendanceDate).catch((error: Error) => setMessage(error.message))
  }, [selectedClassroom, attendanceDate, tab, schoolId, session.token])

  async function saveAttendance(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/attendance`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          date: attendanceDate,
          session: 'full_day',
          records: students.map((row) => ({
            enrollment_id: row.enrollment_id,
            status: marks[row.enrollment_id] ?? 'present',
            client_reference: crypto.randomUUID(),
          })),
        }),
      })
      setMessage('Présence enregistrée pour la classe.')
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Présence impossible à enregistrer.')
    } finally {
      setBusy(false)
    }
  }

  return (
    <main className="px-3 py-3 sm:px-4">
      {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}
      {classrooms.length === 0 ? (
        <p className="rounded-lg bg-white px-3 py-6 text-center text-sm text-neutral-600">Aucune classe attribuée.</p>
      ) : null}

      {tab === 'classe' && classrooms.length > 0 ? (
        <Panel>
          <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
            <select className={`${inputClass} w-auto`} value={selectedClassroom} onChange={(e) => setSelectedClassroom(e.target.value)}>
              {classrooms.map((classroom) => (
                <option key={classroom.id} value={classroom.id}>
                  {classroom.name}
                </option>
              ))}
            </select>
            <input className={`${inputClass} max-w-xs`} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Filtrer l’effectif" />
            <span className="ml-auto text-xs text-neutral-500">{visibleStudents.length} élève(s)</span>
          </div>
          <table className="w-full text-sm">
            <tbody>
              {visibleStudents.map((row) => (
                <tr key={row.enrollment_id} className="border-t border-black/5">
                  <td className="px-3 py-1.5 font-medium">
                    {row.person?.first_name} {row.person?.last_name}
                  </td>
                  <td className="px-3 py-1.5 font-mono text-xs text-neutral-500">{row.person?.public_id}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </Panel>
      ) : null}

      {tab === 'appel' && classrooms.length > 0 ? (
        <Panel>
          <form onSubmit={saveAttendance}>
            <div className="flex flex-wrap items-center gap-2 border-b border-black/5 px-3 py-2">
              <select className={`${inputClass} w-auto`} value={selectedClassroom} onChange={(e) => setSelectedClassroom(e.target.value)}>
                {classrooms.map((classroom) => (
                  <option key={classroom.id} value={classroom.id}>
                    {classroom.name}
                  </option>
                ))}
              </select>
              <input className={`${inputClass} w-auto`} type="date" value={attendanceDate} onChange={(e) => setAttendanceDate(e.target.value)} />
              <input className={`${inputClass} max-w-[10rem]`} value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Filtrer" />
              <button type="submit" disabled={busy || students.length === 0} className={`${btnPrimary} ml-auto`}>
                {busy ? '…' : 'Enregistrer'}
              </button>
            </div>
            {visibleStudents.length === 0 ? (
              <p className="px-3 py-6 text-sm text-neutral-600">Aucun élève.</p>
            ) : (
              <table className="w-full text-sm">
                <tbody>
                  {visibleStudents.map((row) => (
                    <tr key={row.enrollment_id} className="border-t border-black/5">
                      <td className="px-3 py-1.5 font-medium">
                        {row.person?.first_name} {row.person?.last_name}
                      </td>
                      <td className="px-3 py-1.5 text-right">
                        <AttendancePills
                          value={marks[row.enrollment_id] ?? 'present'}
                          onChange={(status) => setMarks({ ...marks, [row.enrollment_id]: status })}
                        />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </form>
        </Panel>
      ) : null}
      {currentClass && tab === 'appel' ? <p className="mt-2 text-[11px] text-neutral-500">Appel de {currentClass.name} — professeur de la classe uniquement.</p> : null}
    </main>
  )
}

function AttendancePills({ value, onChange }: { value: string; onChange: (status: string) => void }) {
  const options = [
    { id: 'present', label: 'P' },
    { id: 'absent', label: 'A' },
    { id: 'late', label: 'R' },
    { id: 'excused', label: 'E' },
  ]
  return (
    <div className="inline-flex gap-0.5" role="group" aria-label="Présence">
      {options.map((option) => {
        const active = value === option.id
        const tone =
          option.id === 'present'
            ? 'bg-fanabe-leaf text-white'
            : option.id === 'absent'
              ? 'bg-fanabe-clay text-white'
              : option.id === 'late'
                ? 'bg-fanabe-gold text-white'
                : 'bg-neutral-700 text-white'
        return (
          <button
            key={option.id}
            type="button"
            title={attendanceLabel(option.id)}
            aria-label={attendanceLabel(option.id)}
            className={`h-7 w-7 rounded text-xs font-semibold ${active ? tone : 'bg-black/5 text-neutral-600'}`}
            onClick={() => onChange(option.id)}
          >
            {option.label}
          </button>
        )
      })}
    </div>
  )
}

function StudentScreen({ session }: { session: Session }) {
  const [overview, setOverview] = useState<StudentOverview | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  useEffect(() => {
    api<StudentOverview>('/api/v1/student/me', { token: session.token })
      .then(setOverview)
      .catch((error: Error) => setMessage(error.message))
  }, [session.token])

  return (
    <main className="mx-auto max-w-2xl px-3 py-3">
      {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}
      <p className="text-sm text-neutral-600">
        {session.person.first_name} · lecture seule
      </p>
      {overview ? (
        <div className="mt-3 space-y-3">
          <div className="grid grid-cols-3 overflow-hidden rounded-lg border border-black/8">
            <Kpi label="Classe" value={overview.enrollment?.classroom?.name ?? '—'} hint={overview.enrollment?.school?.name ?? ''} />
            <Kpi label="Présences" value={String(overview.attendance.length)} hint="14 jours" />
            <Kpi label="Reste" value={formatAr(overview.finance.remaining_amount)} hint={overview.finance.invoice?.number ?? '—'} />
          </div>
          <Panel>
            <h2 className="border-b border-black/5 px-3 py-2 text-sm font-semibold">Présence</h2>
            {overview.attendance.length === 0 ? (
              <p className="px-3 py-3 text-sm text-neutral-600">Rien sur les 14 derniers jours.</p>
            ) : (
              <ul className="divide-y divide-black/5 text-sm">
                {overview.attendance.map((row) => (
                  <li key={row.id} className="flex justify-between px-3 py-1.5">
                    <span>{formatDate(row.date)}</span>
                    <span className="font-medium">{attendanceLabel(row.status)}</span>
                  </li>
                ))}
              </ul>
            )}
          </Panel>
          <Panel className="p-3">
            <h2 className="text-sm font-semibold">Écolage</h2>
            {overview.finance.invoice ? (
              <ul className="mt-2 space-y-1 text-sm">
                {overview.finance.invoice.installments.map((row) => (
                  <li key={row.id} className="flex justify-between">
                    <span>{formatDate(row.due_on)}</span>
                    <span>{formatAr(row.remaining_amount)}</span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mt-2 text-sm text-neutral-600">Pas encore de facture.</p>
            )}
          </Panel>
        </div>
      ) : null}
    </main>
  )
}

function ParentScreen({ session }: { session: Session }) {
  const [children, setChildren] = useState<PersonRow[]>([])
  const [finances, setFinances] = useState<Record<string, ChildFinance>>({})
  const [inbox, setInbox] = useState<ParentInboxMessage[]>([])
  const [message, setMessage] = useState<string | null>(null)

  useEffect(() => {
    api<{ data: PersonRow[] }>('/api/v1/parent/children', { token: session.token })
      .then(async (payload) => {
        setChildren(payload.data)
        const [entries, notes] = await Promise.all([
          Promise.all(
            payload.data.map(async (child) => {
              const finance = await api<ChildFinance>(`/api/v1/parent/children/${child.id}/finance`, {
                token: session.token,
              })
              return [child.id, finance] as const
            }),
          ),
          api<{ data: ParentInboxMessage[] }>('/api/v1/parent/messages', { token: session.token }),
        ])
        setFinances(Object.fromEntries(entries))
        setInbox(notes.data)
      })
      .catch((error: Error) => setMessage(error.message))
  }, [session.token])

  const totalRemaining = Object.values(finances).reduce((sum, row) => sum + row.remaining_amount, 0)

  return (
    <main className="mx-auto max-w-2xl px-3 py-3">
      {message ? <Banner message={message} onClear={() => setMessage(null)} /> : null}
      <div className="flex items-end justify-between gap-3">
        <p className="text-sm text-neutral-600">{session.person.first_name}</p>
        <p className={`text-sm font-semibold ${totalRemaining > 0 ? 'text-fanabe-clay' : 'text-fanabe-leaf'}`}>
          {formatAr(totalRemaining)}
        </p>
      </div>
      {inbox.length > 0 ? (
        <Panel className="mt-3">
          <h2 className="border-b border-black/5 px-3 py-2 text-sm font-semibold">Messages</h2>
          <ul className="divide-y divide-black/5">
            {inbox.map((note) => (
              <li key={note.id} className="px-3 py-2">
                <p className="text-sm font-medium">{note.subject}</p>
                <p className="mt-0.5 whitespace-pre-line text-xs text-neutral-600">{note.body}</p>
              </li>
            ))}
          </ul>
        </Panel>
      ) : null}
      <ul className="mt-3 space-y-2">
        {children.map((child) => {
          const finance = finances[child.id]
          return (
            <li key={child.id}>
              <Panel className="p-3">
                <div className="flex items-baseline justify-between gap-2">
                  <p className="text-sm font-semibold">
                    {child.first_name} {child.last_name}
                  </p>
                  {finance ? (
                    <p className={`text-sm font-semibold ${finance.remaining_amount > 0 ? 'text-fanabe-clay' : 'text-fanabe-leaf'}`}>
                      {formatAr(finance.remaining_amount)}
                    </p>
                  ) : null}
                </div>
                {finance?.data.map((row, index) => (
                  <div key={index} className="mt-2 text-xs text-neutral-600">
                    <p>
                      {row.school?.name}
                      {row.classroom ? ` · ${row.classroom.name}` : ''}
                    </p>
                    {row.invoice ? (
                      <ul className="mt-1 space-y-0.5">
                        {row.invoice.installments.map((item) => (
                          <li key={item.id} className="flex justify-between">
                            <span>{formatDate(item.due_on)}</span>
                            <span>{formatAr(item.remaining_amount)}</span>
                          </li>
                        ))}
                      </ul>
                    ) : (
                      <p className="mt-1">Pas encore de facture.</p>
                    )}
                  </div>
                ))}
              </Panel>
            </li>
          )
        })}
      </ul>
      {children.length === 0 && !message ? (
        <p className="mt-6 text-center text-sm text-neutral-600">Aucun enfant rattaché. Demandez un code à l’école.</p>
      ) : null}
    </main>
  )
}
