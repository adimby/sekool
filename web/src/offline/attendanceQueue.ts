const DB_NAME = 'fanabe'
const STORE = 'attendance_outbox'
const VERSION = 1

export type QueuedAttendance = {
  id: string
  schoolId: string
  date: string
  session: string
  records: Array<{
    enrollment_id: string
    status: string
    reason: string | null
    justification: string | null
    client_reference: string
  }>
  queued_at: number
}

function openDb(): Promise<IDBDatabase> {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, VERSION)
    request.onupgradeneeded = () => {
      const db = request.result
      if (!db.objectStoreNames.contains(STORE)) {
        db.createObjectStore(STORE, { keyPath: 'id' })
      }
    }
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error ?? new Error('IndexedDB indisponible.'))
  })
}

export async function enqueueAttendance(
  item: Omit<QueuedAttendance, 'id' | 'queued_at'>,
): Promise<QueuedAttendance> {
  const row: QueuedAttendance = {
    ...item,
    id: crypto.randomUUID(),
    queued_at: Date.now(),
  }
  const db = await openDb()
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite')
    tx.objectStore(STORE).put(row)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error ?? new Error('File hors ligne impossible.'))
  })
  db.close()
  return row
}

export async function listQueuedAttendance(): Promise<QueuedAttendance[]> {
  const db = await openDb()
  const rows = await new Promise<QueuedAttendance[]>((resolve, reject) => {
    const tx = db.transaction(STORE, 'readonly')
    const request = tx.objectStore(STORE).getAll()
    request.onsuccess = () => resolve((request.result as QueuedAttendance[]) ?? [])
    request.onerror = () => reject(request.error ?? new Error('File hors ligne illisible.'))
  })
  db.close()
  return rows.sort((a, b) => a.queued_at - b.queued_at)
}

export async function removeQueuedAttendance(id: string): Promise<void> {
  const db = await openDb()
  await new Promise<void>((resolve, reject) => {
    const tx = db.transaction(STORE, 'readwrite')
    tx.objectStore(STORE).delete(id)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error ?? new Error('File hors ligne impossible à vider.'))
  })
  db.close()
}

export async function pendingAttendanceCount(): Promise<number> {
  return (await listQueuedAttendance()).length
}

export async function flushAttendanceQueue(
  post: (item: QueuedAttendance) => Promise<void>,
): Promise<number> {
  const rows = await listQueuedAttendance()
  let sent = 0
  for (const row of rows) {
    try {
      await post(row)
      await removeQueuedAttendance(row.id)
      sent += 1
    } catch (error) {
      if (isFatalAttendanceError(error)) {
        await removeQueuedAttendance(row.id)
        throw error
      }
      break
    }
  }
  return sent
}

function isFatalAttendanceError(error: unknown): boolean {
  const message = error instanceof Error ? error.message : ''
  return /introuvable|n’est pas|pas le professeur|403|404|422/i.test(message)
}
