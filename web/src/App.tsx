import { useEffect, useMemo, useState, type FormEvent } from 'react'
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

type ChildFinance = {
  remaining_amount: number
  data: Array<{
    school: { name: string } | null
    classroom: { name: string } | null
    invoice: InvoiceRow | null
    payments: Array<{ amount: number; received_on: string; receipt_number: string | null }>
  }>
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

function formatAr(amount: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(amount)} Ar`
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
        Démo phase 2 — classes, présence, facture, paiement enregistré, solde parent. Pas de SMS, pas
        d&apos;encaissement en ligne.
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
        <br />
        Parent de Fanja : <code>parent.d@fanabe.test</code> / <code>password</code>
      </p>
    </main>
  )
}

const inputClass =
  'mt-1 w-full rounded-lg border border-neutral-300 bg-white px-3 py-2.5 text-base outline-none ring-fanabe-leaf focus:ring-2'
const primaryButtonClass =
  'min-h-11 w-full rounded-lg bg-fanabe-leaf px-4 py-2.5 text-base font-semibold text-white disabled:opacity-60'
const secondaryButtonClass =
  'min-h-11 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium disabled:opacity-60'

type SchoolTab = 'famille' | 'classe' | 'presence' | 'caisse'

function SchoolScreen({
  session,
  onSchoolChange,
}: {
  session: Session
  onSchoolChange: (schoolId: string) => void
}) {
  const schoolId = session.schoolId ?? session.schools[0].id
  const [tab, setTab] = useState<SchoolTab>('caisse')
  const [people, setPeople] = useState<PersonRow[]>([])
  const [yearId, setYearId] = useState<string>('')
  const [classrooms, setClassrooms] = useState<ClassroomRow[]>([])
  const [grades, setGrades] = useState<Array<{ id: string; name: string }>>([])
  const [enrollments, setEnrollments] = useState<EnrollmentRow[]>([])
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
  const [newClassName, setNewClassName] = useState('6ème B')
  const [newClassGrade, setNewClassGrade] = useState('')
  const [selectedClassroom, setSelectedClassroom] = useState('')
  const [attendanceDate, setAttendanceDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [marks, setMarks] = useState<Record<string, string>>({})
  const [selectedEnrollment, setSelectedEnrollment] = useState('')
  const [invoice, setInvoice] = useState<InvoiceRow | null>(null)
  const [paymentAmount, setPaymentAmount] = useState('50000')
  const [receipt, setReceipt] = useState<string | null>(null)

  const auth = useMemo(() => ({ token: session.token }), [session.token])

  async function refresh() {
    const years = await api<{ data: Array<{ id: string; is_current: boolean; label: string }> }>(
      `/api/v1/schools/${schoolId}/years`,
      auth,
    )
    const current = years.data.find((year) => year.is_current) ?? years.data[0]
    setYearId(current?.id ?? '')
    const [list, classList, gradeList, enrollmentList] = await Promise.all([
      api<{ data: PersonRow[] }>(`/api/v1/schools/${schoolId}/people`, auth),
      api<{ data: ClassroomRow[] }>(`/api/v1/schools/${schoolId}/classrooms`, auth),
      api<{ data: Array<{ id: string; name: string }> }>(`/api/v1/schools/${schoolId}/grade-levels`, auth),
      api<{ data: EnrollmentRow[] }>(`/api/v1/schools/${schoolId}/enrollments`, auth),
    ])
    setPeople(list.data)
    setClassrooms(classList.data)
    setGrades(gradeList.data)
    setEnrollments(enrollmentList.data)
    if (!selectedClassroom && classList.data[0]) {
      setSelectedClassroom(classList.data[0].id)
    }
    if (!newClassGrade && gradeList.data[0]) {
      setNewClassGrade(gradeList.data[0].id)
    }
    const active = enrollmentList.data.find((row) => row.status === 'active')
    if (!selectedEnrollment && active) {
      setSelectedEnrollment(active.id)
    }
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

  async function createClassroom(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      await api(`/api/v1/schools/${schoolId}/classrooms`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          school_year_id: yearId,
          grade_level_id: newClassGrade,
          name: newClassName,
        }),
      })
      await refresh()
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
      await refresh()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Affectation impossible.')
    } finally {
      setBusy(false)
    }
  }

  async function loadAttendance(classroomId: string, date: string) {
    const payload = await api<{
      data: Array<{ enrollment_id: string; attendance: { status: string } | null }>
    }>(
      `/api/v1/schools/${schoolId}/attendance?classroom_id=${classroomId}&date=${date}&session=full_day`,
      auth,
    )
    const next: Record<string, string> = {}
    for (const row of payload.data) {
      next[row.enrollment_id] = row.attendance?.status ?? 'present'
    }
    setMarks(next)
  }

  useEffect(() => {
    if (!selectedClassroom || tab !== 'presence') {
      return
    }
    loadAttendance(selectedClassroom, attendanceDate).catch((error: Error) => setMessage(error.message))
  }, [selectedClassroom, attendanceDate, tab, schoolId, session.token])

  async function saveAttendance(event: FormEvent) {
    event.preventDefault()
    setBusy(true)
    setMessage(null)
    try {
      const roster = enrollments.filter((row) => row.classroom_id === selectedClassroom && row.status === 'active')
      await api(`/api/v1/schools/${schoolId}/attendance`, {
        ...auth,
        method: 'POST',
        body: JSON.stringify({
          date: attendanceDate,
          session: 'full_day',
          records: roster.map((row) => ({
            enrollment_id: row.id,
            status: marks[row.id] ?? 'present',
            client_reference: crypto.randomUUID(),
          })),
        }),
      })
      setMessage('Présence enregistrée.')
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Présence impossible à enregistrer.')
    } finally {
      setBusy(false)
    }
  }

  async function loadInvoice(enrollmentId: string) {
    try {
      const payload = await api<{ data: InvoiceRow }>(
        `/api/v1/schools/${schoolId}/enrollments/${enrollmentId}/invoice`,
        auth,
      )
      setInvoice(payload.data)
    } catch {
      setInvoice(null)
    }
  }

  useEffect(() => {
    if (!selectedEnrollment || tab !== 'caisse') {
      return
    }
    loadInvoice(selectedEnrollment).catch(() => setInvoice(null))
  }, [selectedEnrollment, tab, schoolId, session.token])

  async function generateInvoice() {
    setBusy(true)
    setMessage(null)
    setReceipt(null)
    try {
      const payload = await api<{ data: InvoiceRow }>(
        `/api/v1/schools/${schoolId}/enrollments/${selectedEnrollment}/invoices`,
        { ...auth, method: 'POST', body: JSON.stringify({}) },
      )
      setInvoice(payload.data)
      await refresh()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Facture impossible à générer.')
    } finally {
      setBusy(false)
    }
  }

  async function recordPayment(event: FormEvent) {
    event.preventDefault()
    if (!invoice) {
      return
    }
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
            method: 'cash',
            received_on: new Date().toISOString().slice(0, 10),
            idempotency_key: crypto.randomUUID(),
          }),
        },
      )
      setReceipt(payload.data.receipt.number)
      setInvoice(payload.invoice)
      await refresh()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Paiement impossible à enregistrer.')
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

  const roster = enrollments.filter((row) => row.classroom_id === selectedClassroom)

  return (
    <main className="mx-auto max-w-5xl px-6 py-8">
      <label className="block max-w-md text-sm font-medium">
        Établissement
        <select className={inputClass} value={schoolId} onChange={(event) => onSchoolChange(event.target.value)}>
          {session.schools.map((school) => (
            <option key={school.id} value={school.id}>
              {school.name}
            </option>
          ))}
        </select>
      </label>
      <nav className="mt-6 flex flex-wrap gap-2" aria-label="Sections école">
        {(
          [
            ['famille', 'Famille'],
            ['classe', 'Classe'],
            ['presence', 'Présence'],
            ['caisse', 'Caisse'],
          ] as const
        ).map(([id, label]) => (
          <button key={id} type="button" className={tabClass(tab === id)} onClick={() => setTab(id)}>
            {label}
          </button>
        ))}
      </nav>
      {message ? (
        <p className="mt-4 text-sm" role="status">
          {message}
        </p>
      ) : null}

      {tab === 'famille' ? (
        <div className="mt-8 grid gap-8 lg:grid-cols-2">
          <section>
            <h2 className="text-xl font-semibold">Nouvelle famille</h2>
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
        </div>
      ) : null}

      {tab === 'classe' ? (
        <div className="mt-8 grid gap-8 lg:grid-cols-2">
          <section>
            <h2 className="text-xl font-semibold">Créer une classe</h2>
            <form onSubmit={createClassroom} className="mt-4 space-y-3">
              <select className={inputClass} value={newClassGrade} onChange={(e) => setNewClassGrade(e.target.value)}>
                {grades.map((grade) => (
                  <option key={grade.id} value={grade.id}>
                    {grade.name}
                  </option>
                ))}
              </select>
              <input className={inputClass} value={newClassName} onChange={(e) => setNewClassName(e.target.value)} required />
              <button type="submit" disabled={busy || !yearId || !newClassGrade} className={primaryButtonClass}>
                Créer la classe
              </button>
            </form>
            <ul className="mt-6 space-y-2">
              {classrooms.map((classroom) => (
                <li key={classroom.id} className="rounded-lg bg-white px-4 py-3">
                  <p className="font-medium">{classroom.name}</p>
                  <p className="text-sm text-neutral-600">{classroom.grade_level?.name}</p>
                </li>
              ))}
            </ul>
          </section>
          <section>
            <h2 className="text-xl font-semibold">Affecter un élève</h2>
            <ul className="mt-4 space-y-2">
              {enrollments
                .filter((row) => row.status === 'active')
                .map((row) => (
                  <li key={row.id} className="rounded-lg bg-white px-4 py-3">
                    <p className="font-medium">
                      {row.person?.first_name} {row.person?.last_name}
                    </p>
                    <p className="text-sm text-neutral-600">{row.classroom?.name ?? 'Sans classe'}</p>
                    <select
                      className={inputClass}
                      value={row.classroom_id ?? ''}
                      onChange={(event) => {
                        if (event.target.value) {
                          void assignClassroom(row.id, event.target.value)
                        }
                      }}
                    >
                      <option value="">Choisir une classe</option>
                      {classrooms.map((classroom) => (
                        <option key={classroom.id} value={classroom.id}>
                          {classroom.name}
                        </option>
                      ))}
                    </select>
                  </li>
                ))}
            </ul>
          </section>
        </div>
      ) : null}

      {tab === 'presence' ? (
        <section className="mt-8">
          <h2 className="text-xl font-semibold">Appel du jour</h2>
          <form onSubmit={saveAttendance} className="mt-4 space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
              <select className={inputClass} value={selectedClassroom} onChange={(e) => setSelectedClassroom(e.target.value)}>
                {classrooms.map((classroom) => (
                  <option key={classroom.id} value={classroom.id}>
                    {classroom.name}
                  </option>
                ))}
              </select>
              <input className={inputClass} type="date" value={attendanceDate} onChange={(e) => setAttendanceDate(e.target.value)} />
            </div>
            {roster.length === 0 ? (
              <p className="text-sm text-neutral-600">Aucun élève dans cette classe.</p>
            ) : (
              <ul className="space-y-2">
                {roster.map((row) => (
                  <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-4 py-3">
                    <p className="font-medium">
                      {row.person?.first_name} {row.person?.last_name}
                    </p>
                    <select
                      className={inputClass + ' mt-0 max-w-40'}
                      value={marks[row.id] ?? 'present'}
                      onChange={(e) => setMarks({ ...marks, [row.id]: e.target.value })}
                    >
                      <option value="present">Présent</option>
                      <option value="absent">Absent</option>
                      <option value="late">En retard</option>
                      <option value="excused">Excusé</option>
                    </select>
                  </li>
                ))}
              </ul>
            )}
            <button type="submit" disabled={busy || roster.length === 0} className={primaryButtonClass}>
              Enregistrer la présence
            </button>
          </form>
        </section>
      ) : null}

      {tab === 'caisse' ? (
        <section className="mt-8 grid gap-8 lg:grid-cols-2">
          <div>
            <h2 className="text-xl font-semibold">Facture et paiement</h2>
            <p className="mt-2 text-sm text-neutral-700">
              FANABE enregistre un paiement déjà reçu (espèces, mobile money, virement). Il n&apos;encaisse
              rien en ligne.
            </p>
            <label className="mt-4 block text-sm font-medium">
              Élève
              <select className={inputClass} value={selectedEnrollment} onChange={(e) => setSelectedEnrollment(e.target.value)}>
                {enrollments
                  .filter((row) => row.status === 'active')
                  .map((row) => (
                    <option key={row.id} value={row.id}>
                      {row.person?.first_name} {row.person?.last_name}
                      {row.invoice ? ` · reste ${formatAr(row.invoice.remaining_amount)}` : ''}
                    </option>
                  ))}
              </select>
            </label>
            <div className="mt-4 flex flex-wrap gap-2">
              <button type="button" disabled={busy || !selectedEnrollment} className={secondaryButtonClass} onClick={generateInvoice}>
                Générer la facture
              </button>
              <button type="button" className={secondaryButtonClass} onClick={() => downloadCsv().catch((error: Error) => setMessage(error.message))}>
                Export CSV
              </button>
            </div>
            {invoice ? (
              <div className="mt-4 rounded-lg bg-white p-4 text-sm">
                <p>
                  Facture <strong>{invoice.number}</strong> · {invoice.status}
                </p>
                <p className="mt-1">Net {formatAr(invoice.net_amount)} · payé {formatAr(invoice.paid_amount)} · reste {formatAr(invoice.remaining_amount)}</p>
                <ul className="mt-3 space-y-1">
                  {invoice.installments.map((row) => (
                    <li key={row.id}>
                      Échéance {row.due_on} · {formatAr(row.remaining_amount)} restant
                    </li>
                  ))}
                </ul>
              </div>
            ) : (
              <p className="mt-4 text-sm text-neutral-600">Pas encore de facture pour cet élève.</p>
            )}
          </div>
          <form onSubmit={recordPayment} className="space-y-3">
            <h3 className="text-lg font-semibold">Enregistrer un paiement</h3>
            <label className="block text-sm font-medium">
              Montant (Ariary)
              <input className={inputClass} type="number" min={1} step={1} value={paymentAmount} onChange={(e) => setPaymentAmount(e.target.value)} required />
            </label>
            <button type="submit" disabled={busy || !invoice} className={primaryButtonClass}>
              {busy ? 'Enregistrement…' : 'Enregistrer (espèces)'}
            </button>
            {receipt ? (
              <p className="rounded-lg bg-white p-3 text-sm" role="status">
                Reçu émis : <strong>{receipt}</strong>
              </p>
            ) : null}
          </form>
        </section>
      ) : null}
    </main>
  )
}

function ParentScreen({ session }: { session: Session }) {
  const [children, setChildren] = useState<PersonRow[]>([])
  const [finances, setFinances] = useState<Record<string, ChildFinance>>({})
  const [message, setMessage] = useState<string | null>(null)

  useEffect(() => {
    api<{ data: PersonRow[] }>('/api/v1/parent/children', { token: session.token })
      .then(async (payload) => {
        setChildren(payload.data)
        const entries = await Promise.all(
          payload.data.map(async (child) => {
            const finance = await api<ChildFinance>(`/api/v1/parent/children/${child.id}/finance`, {
              token: session.token,
            })
            return [child.id, finance] as const
          }),
        )
        setFinances(Object.fromEntries(entries))
      })
      .catch((error: Error) => setMessage(error.message))
  }, [session.token])

  return (
    <main className="mx-auto max-w-2xl px-6 py-8">
      <h2 className="text-xl font-semibold">Mes enfants</h2>
      <p className="mt-2 text-sm text-neutral-700">Identifiant FANABE : {session.person.public_id}</p>
      {message ? <p className="mt-4 text-sm">{message}</p> : null}
      <ul className="mt-6 space-y-4">
        {children.map((child) => {
          const finance = finances[child.id]
          return (
            <li key={child.id} className="rounded-lg bg-white px-4 py-3">
              <p className="font-medium">
                {child.first_name} {child.last_name}
              </p>
              <p className="text-sm text-neutral-600">{child.public_id}</p>
              {finance ? (
                <div className="mt-3 text-sm">
                  <p>
                    Solde restant : <strong>{formatAr(finance.remaining_amount)}</strong>
                  </p>
                  {finance.data.map((row, index) => (
                    <div key={index} className="mt-2 border-t border-black/5 pt-2">
                      <p>
                        {row.school?.name}
                        {row.classroom ? ` · ${row.classroom.name}` : ''}
                      </p>
                      {row.invoice ? (
                        <p>
                          Facture {row.invoice.number} · reste {formatAr(row.invoice.remaining_amount)}
                        </p>
                      ) : (
                        <p>Pas encore de facture.</p>
                      )}
                      {row.invoice?.installments.map((item) => (
                        <p key={item.id} className="text-neutral-600">
                          Échéance {item.due_on} · {formatAr(item.remaining_amount)} restant
                        </p>
                      ))}
                    </div>
                  ))}
                </div>
              ) : null}
            </li>
          )
        })}
      </ul>
      {children.length === 0 && !message ? (
        <p className="mt-6 text-sm text-neutral-600">Aucun enfant n&apos;est encore rattaché à ce compte.</p>
      ) : null}
    </main>
  )
}
