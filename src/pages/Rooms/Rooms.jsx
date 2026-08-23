import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Ban,
  CalendarDays,
  CalendarPlus,
  Clock3,
  Download,
  History,
  MapPin,
  MonitorUp,
  Pencil,
  Plus,
  Search,
  Users,
  X,
} from 'lucide-react';
import { prmApi } from '@/api/client';
import { useCurrentUser } from '@/hooks/useCurrentUser';
import { useRouteTitle } from '@/hooks/useDocumentTitle';
import { ContentLoadingSpinner } from '@/components/LoadingSpinner';
import {
  contextPayload,
  contextValue,
  formatBookingTime,
  groupBookingsByDate,
  localDateTimeIso,
  localDateValue,
  rangeForDate,
} from './roomUtils';

const STATUS_LABELS = {
  confirmed: 'Bevestigd',
  completed: 'Afgelopen',
  cancelled: 'Geannuleerd',
};
const EMPTY_CONTEXTS = [];

export default function Rooms() {
  useRouteTitle('Ruimtes');
  const queryClient = useQueryClient();
  const { data: currentUser } = useCurrentUser();
  const [tab, setTab] = useState('availability');
  const [selectedDate, setSelectedDate] = useState(() => localDateValue());
  const [managerView, setManagerView] = useState('day');
  const [bookingModal, setBookingModal] = useState(null);
  const [activityBooking, setActivityBooking] = useState(null);
  const [message, setMessage] = useState('');

  const canManage = Boolean(currentUser?.can_manage_accommodatie);
  const isAdmin = Boolean(currentUser?.is_admin);
  const dayRange = useMemo(() => rangeForDate(selectedDate, 1), [selectedDate]);
  const managerRange = useMemo(
    () => rangeForDate(selectedDate, managerView === 'week' ? 7 : 1),
    [managerView, selectedDate],
  );

  const roomsQuery = useQuery({
    queryKey: ['rooms', isAdmin],
    queryFn: async () => (await prmApi.getRooms(isAdmin ? { include_archived: true } : {})).data,
  });
  const contextsQuery = useQuery({
    queryKey: ['rooms', 'booking-contexts'],
    queryFn: async () => (await prmApi.getRoomBookingContexts()).data,
  });
  const availabilityQuery = useQuery({
    queryKey: ['rooms', 'availability', dayRange],
    queryFn: async () => (await prmApi.getRoomAvailability(dayRange)).data,
    enabled: tab === 'availability',
  });
  const mineQuery = useQuery({
    queryKey: ['rooms', 'mine'],
    queryFn: async () => (await prmApi.getMyRoomBookings()).data,
    enabled: tab === 'mine',
  });
  const managedQuery = useQuery({
    queryKey: ['rooms', 'managed', managerRange],
    queryFn: async () => (await prmApi.getManagedRoomBookings(managerRange)).data,
    enabled: canManage && tab === 'manage',
  });

  const invalidateRoomData = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ['rooms'] }),
      queryClient.invalidateQueries({ queryKey: ['narrowcasting'] }),
    ]);
  };

  const actionMutation = useMutation({
    mutationFn: async ({ action, booking, reason = '' }) => {
      if (action === 'cancel') return prmApi.cancelRoomBooking(booking.id, reason);
      if (action === 'extend') return prmApi.extendRoomBooking(booking.id);
      return prmApi.setRoomPresentationOverride(booking.id, action);
    },
    onSuccess: async (response) => {
      await invalidateRoomData();
      setMessage(response.data?.notification?.status === 'no_email'
        ? 'Opgeslagen; de houder heeft geen bruikbaar e-mailadres.'
        : 'De reservering is bijgewerkt.');
    },
  });

  const handleAction = (action, booking) => {
    let reason = '';
    if (action === 'cancel') {
      if (canManage) {
        reason = window.prompt('Waarom annuleer je deze reservering?') || '';
        if (!reason) return;
      } else if (!window.confirm('Weet je zeker dat je deze reservering wilt annuleren?')) {
        return;
      }
    }
    actionMutation.mutate({ action, booking, reason });
  };

  const downloadCalendar = async (booking) => {
    const response = await prmApi.downloadRoomBookingCalendar(booking.id);
    const url = URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = `rondo-reservering-${booking.id}.ics`;
    link.click();
    URL.revokeObjectURL(url);
  };

  const tabs = [
    ['availability', 'Beschikbaarheid'],
    ['mine', 'Mijn reserveringen'],
    ...(canManage ? [['manage', 'Accommodatiebeheer']] : []),
    ...(isAdmin ? [['rooms', 'Ruimtes instellen']] : []),
  ];

  const rooms = roomsQuery.data || [];
  const activeRooms = rooms.filter((room) => !room.archived);
  const contexts = contextsQuery.data || [];

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100">Ruimtes</h1>
          <p className="mt-1 text-gray-600 dark:text-gray-400">
            Reserveer een clubruimte voor je commissie of jaarlaagoverleg.
          </p>
        </div>
        {tab === 'availability' && contexts.length > 0 && (
          <button type="button" className="btn-primary" onClick={() => setBookingModal({ manager: false })}>
            <CalendarPlus className="mr-2 h-4 w-4" />
            Reserveren
          </button>
        )}
        {tab === 'manage' && (
          <button type="button" className="btn-primary" onClick={() => setBookingModal({ manager: true })}>
            <Plus className="mr-2 h-4 w-4" />
            Reservering of blokkade
          </button>
        )}
      </header>

      <nav className="flex gap-2 overflow-x-auto border-b border-gray-200 pb-2 dark:border-gray-700" aria-label="Ruimtes">
        {tabs.map(([value, label]) => (
          <button
            key={value}
            type="button"
            className={tab === value ? 'btn-primary whitespace-nowrap' : 'btn-tertiary whitespace-nowrap'}
            aria-pressed={tab === value}
            onClick={() => setTab(value)}
          >
            {label}
          </button>
        ))}
      </nav>

      {message && (
        <div className="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
          {message}
        </div>
      )}
      {actionMutation.error && <ErrorNotice error={actionMutation.error} />}

      {tab === 'availability' && (
        <AvailabilityView
          rooms={activeRooms}
          bookings={availabilityQuery.data || []}
          date={selectedDate}
          setDate={setSelectedDate}
          loading={roomsQuery.isLoading || availabilityQuery.isLoading}
          canBook={contexts.length > 0}
          onBook={(roomId) => setBookingModal({ manager: false, roomId })}
        />
      )}

      {tab === 'mine' && (
        <BookingCollection
          title="Mijn reserveringen"
          bookings={mineQuery.data || []}
          loading={mineQuery.isLoading}
          manager={false}
          onEdit={(booking) => setBookingModal({ manager: false, booking })}
          onAction={handleAction}
          onCalendar={downloadCalendar}
        />
      )}

      {tab === 'manage' && canManage && (
        <ManagerView
          bookings={managedQuery.data || []}
          loading={managedQuery.isLoading}
          date={selectedDate}
          setDate={setSelectedDate}
          view={managerView}
          setView={setManagerView}
          onEdit={(booking) => setBookingModal({ manager: true, booking })}
          onAction={handleAction}
          onCalendar={downloadCalendar}
          onActivity={setActivityBooking}
        />
      )}

      {tab === 'rooms' && isAdmin && (
        <RoomAdministration rooms={rooms} onSaved={invalidateRoomData} />
      )}

      {bookingModal && (
        <BookingForm
          rooms={activeRooms}
          memberContexts={contexts}
          manager={bookingModal.manager}
          initialRoomId={bookingModal.roomId}
          booking={bookingModal.booking}
          onClose={() => setBookingModal(null)}
          onSaved={async (booking) => {
            await invalidateRoomData();
            setBookingModal(null);
            setMessage(booking.notification?.status === 'no_email'
              ? 'Reservering opgeslagen; de houder heeft geen bruikbaar e-mailadres.'
              : 'De reservering is opgeslagen.');
          }}
        />
      )}

      {activityBooking && (
        <ActivityDialog booking={activityBooking} onClose={() => setActivityBooking(null)} />
      )}
    </div>
  );
}

function AvailabilityView({ rooms, bookings, date, setDate, loading, canBook, onBook }) {
  const [minimumCapacity, setMinimumCapacity] = useState(0);
  const [facility, setFacility] = useState('');
  const byRoom = useMemo(() => {
    const map = new Map();
    for (const booking of bookings) {
      if (!map.has(booking.room_id)) map.set(booking.room_id, []);
      map.get(booking.room_id).push(booking);
    }
    return map;
  }, [bookings]);
  const facilities = useMemo(
    () => [...new Set(rooms.flatMap((room) => room.facilities))].sort((left, right) => left.localeCompare(right, 'nl')),
    [rooms],
  );
  const filteredRooms = rooms.filter((room) => room.capacity >= minimumCapacity && (!facility || room.facilities.includes(facility)));

  return (
    <section className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-3">
        <label className="block">
          <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Datum</span>
          <input type="date" className="input w-full" value={date} onChange={(event) => setDate(event.target.value)} />
        </label>
        <label className="block">
          <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Minimaal aantal personen</span>
          <input type="number" min="0" className="input w-full" value={minimumCapacity} onChange={(event) => setMinimumCapacity(Number(event.target.value))} />
        </label>
        <label className="block">
          <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Faciliteit</span>
          <select className="input w-full" value={facility} onChange={(event) => setFacility(event.target.value)}>
            <option value="">Alle faciliteiten</option>
            {facilities.map((item) => <option key={item} value={item}>{item}</option>)}
          </select>
        </label>
      </div>
      {!canBook && (
        <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
          Je kunt de beschikbaarheid bekijken, maar reserveren kan alleen met een actuele vrijwilligersfunctie in een commissie of team.
        </div>
      )}
      {loading ? <ContentLoadingSpinner /> : filteredRooms.length === 0 ? (
        <EmptyState>Geen ruimte voldoet aan deze filters.</EmptyState>
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {filteredRooms.map((room) => {
            const roomBookings = byRoom.get(room.id) || [];
            return (
              <article key={room.id} className="card p-5">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{room.name}</h2>
                    {room.location && <p className="mt-1 flex items-center gap-1 text-sm text-gray-500 dark:text-gray-400"><MapPin className="h-4 w-4" />{room.location}</p>}
                  </div>
                  {canBook && room.booking_enabled && (
                    <button type="button" className="btn-tertiary text-sm" onClick={() => onBook(room.id)}>Reserveren</button>
                  )}
                </div>
                {room.description && <p className="mt-3 text-sm text-gray-600 dark:text-gray-300">{room.description}</p>}
                {room.member_instructions && <p className="mt-2 rounded-lg bg-blue-50 p-2 text-sm text-blue-800 dark:bg-blue-950 dark:text-blue-200">{room.member_instructions}</p>}
                <div className="mt-3 flex flex-wrap gap-2 text-xs text-gray-600 dark:text-gray-300">
                  {room.capacity > 0 && <span className="rounded-full bg-gray-100 px-2 py-1 dark:bg-gray-800"><Users className="mr-1 inline h-3 w-3" />{room.capacity}</span>}
                  {room.facilities.map((facility) => <span key={facility} className="rounded-full bg-gray-100 px-2 py-1 dark:bg-gray-800">{facility}</span>)}
                  {room.display_id > 0 && !room.display_online && <span className="rounded-full bg-amber-100 px-2 py-1 text-amber-800 dark:bg-amber-950 dark:text-amber-200">Presentatiescherm offline</span>}
                </div>
                <div className="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                  <h3 className="text-sm font-medium text-gray-800 dark:text-gray-200">Bezet</h3>
                  <p className="mt-1 text-sm font-medium text-emerald-700 dark:text-emerald-300">{nextAvailableLabel(room, roomBookings, date)}</p>
                  {roomBookings.length === 0 ? (
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Geen reserveringen.</p>
                  ) : (
                    <ul className="mt-2 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                      {roomBookings.map((booking) => (
                        <li key={booking.id} className="flex items-center gap-2"><Clock3 className="h-4 w-4" />{formatBookingTime(booking.start_datetime, { includeDate: false })}–{formatBookingTime(booking.end_datetime, { includeDate: false })}</li>
                      ))}
                    </ul>
                  )}
                </div>
              </article>
            );
          })}
        </div>
      )}
    </section>
  );
}

function BookingCollection({ title, bookings, loading, manager, onEdit, onAction, onCalendar, onActivity }) {
  if (loading) return <ContentLoadingSpinner />;
  if (!bookings.length) return <EmptyState>Er zijn geen reserveringen in deze periode.</EmptyState>;
  return (
    <section className="space-y-3">
      {title && <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">{title}</h2>}
      {bookings.map((booking) => (
        <BookingCard
          key={booking.id}
          booking={booking}
          manager={manager}
          onEdit={onEdit}
          onAction={onAction}
          onCalendar={onCalendar}
          onActivity={onActivity}
        />
      ))}
    </section>
  );
}

function BookingCard({ booking, manager, onEdit, onAction, onCalendar, onActivity }) {
  const now = Date.now();
  const future = new Date(booking.start_datetime).getTime() > now;
  const active = new Date(booking.start_datetime).getTime() <= now && new Date(booking.effective_end_datetime).getTime() > now;
  const canChange = booking.status === 'confirmed' && (manager || future);
  return (
    <article className={`card p-4 ${booking.status === 'cancelled' ? 'opacity-65' : ''}`}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="font-semibold text-gray-900 dark:text-gray-100">{booking.room_name}</h3>
            <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300">{STATUS_LABELS[booking.status] || booking.status}</span>
          </div>
          <p className="mt-1 text-sm text-gray-700 dark:text-gray-300">{formatBookingTime(booking.start_datetime)}–{formatBookingTime(booking.effective_end_datetime, { includeDate: false })}</p>
          <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{booking.context_label || 'Beheerblokkade'} · {booking.purpose}</p>
          {manager && booking.holder_name && <p className="mt-1 text-sm font-medium text-gray-700 dark:text-gray-300">Houder: {booking.holder_name}</p>}
          {manager && booking.holder_email && <a className="mt-1 inline-block text-sm text-emerald-700 hover:underline dark:text-emerald-300" href={`mailto:${booking.holder_email}`}>Mail de houder</a>}
          {booking.cancellation_reason && <p className="mt-2 text-sm text-red-700 dark:text-red-300">Reden: {booking.cancellation_reason}</p>}
        </div>
        <div className="flex flex-wrap gap-2">
          {canChange && <button type="button" className="btn-tertiary p-2" onClick={() => onEdit(booking)} aria-label="Reservering bewerken"><Pencil className="h-4 w-4" /></button>}
          <button type="button" className="btn-tertiary p-2" onClick={() => onCalendar(booking)} aria-label="Kalenderbestand downloaden"><Download className="h-4 w-4" /></button>
          {booking.presentation?.available_now && <a className="btn-primary p-2" href="/presenteren" aria-label="Presenteren"><MonitorUp className="h-4 w-4" /></a>}
          {active && booking.status === 'confirmed' && <button type="button" className="btn-tertiary text-sm" onClick={() => onAction('extend', booking)}>Verleng</button>}
          {canChange && <button type="button" className="btn-tertiary p-2 text-red-700 dark:text-red-300" onClick={() => onAction('cancel', booking)} aria-label="Reservering annuleren"><Ban className="h-4 w-4" /></button>}
          {manager && active && booking.status === 'confirmed' && (
            <button type="button" className="btn-tertiary text-sm" onClick={() => onAction(booking.presentation?.available_now ? 'stop' : 'start', booking)}>
              {booking.presentation?.available_now ? 'Presentatie stoppen' : 'Presentatie toestaan'}
            </button>
          )}
          {manager && onActivity && <button type="button" className="btn-tertiary p-2" onClick={() => onActivity(booking)} aria-label="Wijzigingsgeschiedenis"><History className="h-4 w-4" /></button>}
        </div>
      </div>
    </article>
  );
}

function ManagerView({ bookings, loading, date, setDate, view, setView, ...actions }) {
  const [roomId, setRoomId] = useState('');
  const [holder, setHolder] = useState('');
  const [status, setStatus] = useState('');
  const [presentation, setPresentation] = useState('');
  const roomOptions = useMemo(
    () => [...new Map(bookings.map((booking) => [booking.room_id, booking.room_name])).entries()],
    [bookings],
  );
  const filtered = useMemo(() => bookings.filter((booking) => (
    (!roomId || booking.room_id === Number(roomId))
    && (!holder || booking.holder_name.toLocaleLowerCase('nl').includes(holder.toLocaleLowerCase('nl')))
    && (!status || booking.status === status)
    && (!presentation || (presentation === 'active') === Boolean(booking.presentation?.available_now))
  )), [bookings, holder, presentation, roomId, status]);
  const grouped = useMemo(() => groupBookingsByDate(filtered), [filtered]);
  return (
    <section className="space-y-5">
      <div className="flex flex-wrap items-end gap-3">
        <label>
          <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Vanaf</span>
          <input type="date" className="input" value={date} onChange={(event) => setDate(event.target.value)} />
        </label>
        <div className="flex gap-2" aria-label="Weergave">
          <button type="button" className={view === 'day' ? 'btn-primary' : 'btn-tertiary'} onClick={() => setView('day')}>Dag</button>
          <button type="button" className={view === 'week' ? 'btn-primary' : 'btn-tertiary'} onClick={() => setView('week')}>Week</button>
        </div>
      </div>
      <div className="grid gap-3 md:grid-cols-4">
        <label><span className="mb-1 block text-sm font-medium">Ruimte</span><select className="input w-full" value={roomId} onChange={(event) => setRoomId(event.target.value)}><option value="">Alle ruimtes</option>{roomOptions.map(([id, name]) => <option key={id} value={id}>{name}</option>)}</select></label>
        <label><span className="mb-1 block text-sm font-medium">Houder</span><input className="input w-full" value={holder} onChange={(event) => setHolder(event.target.value)} placeholder="Zoek op naam" /></label>
        <label><span className="mb-1 block text-sm font-medium">Status</span><select className="input w-full" value={status} onChange={(event) => setStatus(event.target.value)}><option value="">Alle statussen</option><option value="confirmed">Bevestigd</option><option value="completed">Afgelopen</option><option value="cancelled">Geannuleerd</option></select></label>
        <label><span className="mb-1 block text-sm font-medium">Presentatie</span><select className="input w-full" value={presentation} onChange={(event) => setPresentation(event.target.value)}><option value="">Alle</option><option value="active">Nu actief</option><option value="inactive">Niet actief</option></select></label>
      </div>
      {loading ? <ContentLoadingSpinner /> : grouped.length === 0 ? <EmptyState>Geen reserveringen in deze periode.</EmptyState> : grouped.map(([day, dayBookings]) => (
        <BookingCollection
          key={day}
          title={new Intl.DateTimeFormat('nl-NL', { weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(`${day}T12:00:00`))}
          bookings={dayBookings}
          manager
          {...actions}
        />
      ))}
    </section>
  );
}

function BookingForm({ rooms, memberContexts, manager, initialRoomId, booking, onClose, onSaved }) {
  const initialStart = booking ? new Date(booking.start_datetime) : new Date();
  if (!booking) initialStart.setMinutes(Math.ceil(initialStart.getMinutes() / 15) * 15, 0, 0);
  const initialEnd = booking ? new Date(booking.end_datetime) : new Date(initialStart.getTime() + 60 * 60 * 1000);
  const [bookingType, setBookingType] = useState(booking?.booking_type || 'member_reservation');
  const [roomId, setRoomId] = useState(String(booking?.room_id || initialRoomId || rooms[0]?.id || ''));
  const [date, setDate] = useState(localDateValue(initialStart));
  const [startTime, setStartTime] = useState(initialStart.toTimeString().slice(0, 5));
  const [endTime, setEndTime] = useState(initialEnd.toTimeString().slice(0, 5));
  const [purpose, setPurpose] = useState(booking?.purpose || '');
  const [privateNotes, setPrivateNotes] = useState(booking?.private_notes || '');
  const [holder, setHolder] = useState(booking?.holder_user_id ? { id: booking.holder_user_id, display_name: booking.holder_name } : null);
  const [holderSearch, setHolderSearch] = useState('');
  const [presenterSearch, setPresenterSearch] = useState('');
  const [presenters, setPresenters] = useState(() => (booking?.authorized_presenter_user_ids || []).map((id) => ({ id, display_name: `Gebruiker ${id}` })));
  const [contextKey, setContextKey] = useState(() => booking?.booking_context_type === 'commissie'
    ? `commissie:${booking.commissie_id}`
    : booking?.age_group_key ? `age_group:${booking.age_group_key}` : '');
  const selectableRooms = useMemo(
    () => rooms.filter((room) => bookingType === 'management_block' || room.booking_enabled || room.id === booking?.room_id),
    [booking?.room_id, bookingType, rooms],
  );

  const holderSearchQuery = useQuery({
    queryKey: ['users', 'search', holderSearch],
    queryFn: async () => (await prmApi.searchUsers(holderSearch)).data,
    enabled: manager && holderSearch.trim().length >= 2,
  });
  const presenterSearchQuery = useQuery({
    queryKey: ['users', 'search', presenterSearch],
    queryFn: async () => (await prmApi.searchUsers(presenterSearch)).data,
    enabled: presenterSearch.trim().length >= 2,
  });
  const holderContextsQuery = useQuery({
    queryKey: ['rooms', 'booking-contexts', holder?.id],
    queryFn: async () => (await prmApi.getManagedRoomBookingContexts(holder.id)).data,
    enabled: manager && bookingType === 'member_reservation' && Boolean(holder?.id),
  });
  const baseContexts = manager ? (holderContextsQuery.data || EMPTY_CONTEXTS) : memberContexts;
  const contexts = useMemo(() => {
    if (!booking?.booking_context_type) return baseContexts;
    const existing = {
      type: booking.booking_context_type,
      commissie_id: booking.commissie_id || null,
      age_group_key: booking.age_group_key || null,
      label: booking.context_label,
    };
    return baseContexts.some((context) => contextValue(context) === contextValue(existing))
      ? baseContexts
      : [existing, ...baseContexts];
  }, [baseContexts, booking]);

  useEffect(() => {
    if (!contexts.some((context) => contextValue(context) === contextKey)) {
      setContextKey(contexts[0] ? contextValue(contexts[0]) : '');
    }
  }, [contextKey, contexts]);

  useEffect(() => {
    if (!selectableRooms.some((room) => String(room.id) === roomId)) {
      setRoomId(String(selectableRooms[0]?.id || ''));
    }
  }, [roomId, selectableRooms]);

  const mutation = useMutation({
    mutationFn: async (payload) => {
      if (booking) return prmApi.updateRoomBooking(booking.id, payload);
      return manager ? prmApi.createManagedRoomBooking(payload) : prmApi.createRoomBooking(payload);
    },
    onSuccess: (response) => onSaved(response.data),
  });

  const submit = (event) => {
    event.preventDefault();
    const context = contexts.find((item) => contextValue(item) === contextKey);
    mutation.mutate({
      room_id: Number(roomId),
      start_datetime: localDateTimeIso(date, startTime),
      end_datetime: localDateTimeIso(date, endTime),
      booking_type: bookingType,
      holder_user_id: manager && bookingType === 'member_reservation' ? holder?.id : undefined,
      purpose,
      private_notes: privateNotes,
      authorized_presenter_user_ids: bookingType === 'member_reservation' ? presenters.map((presenter) => presenter.id) : [],
      ...contextPayload(bookingType === 'member_reservation' ? context : null),
    });
  };

  const canSubmit = roomId && purpose.trim() && (bookingType === 'management_block' || (contextKey && (!manager || holder?.id)));
  const selectedRoom = selectableRooms.find((room) => String(room.id) === roomId);
  const selectedContext = contexts.find((context) => contextValue(context) === contextKey);

  return (
    <Dialog title={booking ? 'Reservering bewerken' : manager ? 'Reservering of blokkade toevoegen' : 'Ruimte reserveren'} onClose={onClose}>
      <form className="space-y-4" onSubmit={submit}>
        {manager && !booking && (
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Type</span>
            <select className="input w-full" value={bookingType} onChange={(event) => setBookingType(event.target.value)}>
              <option value="member_reservation">Reservering voor een vrijwilliger</option>
              <option value="management_block">Beheerblokkade</option>
            </select>
          </label>
        )}

        {manager && bookingType === 'member_reservation' && !booking && (
          <UserSearch
            label="Reserveringshouder"
            value={holderSearch}
            setValue={setHolderSearch}
            results={holderSearchQuery.data || []}
            selected={holder ? [holder] : []}
            onSelect={(user) => { setHolder(user); setHolderSearch(''); }}
            onRemove={() => setHolder(null)}
            single
          />
        )}

        {bookingType === 'member_reservation' && (
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Commissie of jaarlaag</span>
            <select className="input w-full" value={contextKey} onChange={(event) => setContextKey(event.target.value)} disabled={!contexts.length} required>
              {!contexts.length && <option value="">Geen kwalificerende groep</option>}
              {contexts.map((context) => <option key={contextValue(context)} value={contextValue(context)}>{context.label}</option>)}
            </select>
          </label>
        )}

        {canSubmit && (
          <div className="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <strong className="block">Samenvatting</strong>
            <span className="mt-1 block">{selectedContext?.label || 'Beheerblokkade'} · {selectedRoom?.name} · {date} {startTime}–{endTime}</span>
            <span className="mt-1 block">{selectedRoom?.display_id && selectedRoom?.presentation_controlled ? 'Presentatiescherm beschikbaar tijdens de reservering.' : 'Geen reserveringsgestuurd presentatiescherm.'}</span>
            {selectedRoom?.member_instructions && <span className="mt-1 block">Instructie: {selectedRoom.member_instructions}</span>}
          </div>
        )}

        <label className="block">
          <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Ruimte</span>
          <select className="input w-full" value={roomId} onChange={(event) => setRoomId(event.target.value)} required>
            {selectableRooms.map((room) => <option key={room.id} value={room.id}>{room.name}</option>)}
          </select>
        </label>

        <div className="grid gap-3 sm:grid-cols-3">
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Datum</span>
            <input type="date" className="input w-full" value={date} onChange={(event) => setDate(event.target.value)} required />
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Start</span>
            <input type="time" step="900" className="input w-full" value={startTime} onChange={(event) => setStartTime(event.target.value)} required />
          </label>
          <label className="block">
            <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Einde</span>
            <input type="time" step="900" className="input w-full" value={endTime} onChange={(event) => setEndTime(event.target.value)} required />
          </label>
        </div>

        <label className="block">
          <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Doel</span>
          <input className="input w-full" value={purpose} onChange={(event) => setPurpose(event.target.value)} maxLength={160} required />
        </label>
        <label className="block">
          <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Privénotities</span>
          <textarea className="input min-h-24 w-full" value={privateNotes} onChange={(event) => setPrivateNotes(event.target.value)} />
        </label>

        {bookingType === 'member_reservation' && (
          <UserSearch
            label="Extra presentatoren"
            value={presenterSearch}
            setValue={setPresenterSearch}
            results={presenterSearchQuery.data || []}
            selected={presenters}
            onSelect={(user) => {
              setPresenters((current) => current.some((item) => item.id === user.id) ? current : [...current, user]);
              setPresenterSearch('');
            }}
            onRemove={(user) => setPresenters((current) => current.filter((item) => item.id !== user.id))}
          />
        )}

        {mutation.error && <ErrorNotice error={mutation.error} />}
        <div className="flex justify-end gap-2 border-t border-gray-200 pt-4 dark:border-gray-700">
          <button type="button" className="btn-tertiary" onClick={onClose}>Annuleren</button>
          <button type="submit" className="btn-primary" disabled={!canSubmit || mutation.isPending}>{mutation.isPending ? 'Opslaan…' : 'Opslaan'}</button>
        </div>
      </form>
    </Dialog>
  );
}

function UserSearch({ label, value, setValue, results, selected, onSelect, onRemove, single = false }) {
  return (
    <div>
      <label className="block">
        <span className="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">{label}</span>
        <div className="relative">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
          <input className="input w-full pl-9" value={value} onChange={(event) => setValue(event.target.value)} placeholder="Zoek op naam…" />
        </div>
      </label>
      {value.trim().length >= 2 && results.length > 0 && (
        <div className="mt-1 max-h-40 overflow-auto rounded-lg border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-gray-900">
          {results.map((user) => <button key={user.id} type="button" className="block w-full rounded px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-800" onClick={() => onSelect(user)}>{user.display_name}</button>)}
        </div>
      )}
      {selected.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-2">
          {selected.map((user) => (
            <span key={user.id} className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-sm dark:bg-gray-800">
              {user.display_name}
              <button type="button" onClick={() => onRemove(user)} aria-label={`${user.display_name} verwijderen`}><X className="h-3 w-3" /></button>
            </span>
          ))}
        </div>
      )}
      {single && selected.length > 0 && <p className="mt-1 text-xs text-gray-500">Kies een andere naam om de houder te wijzigen.</p>}
    </div>
  );
}

function RoomAdministration({ rooms, onSaved }) {
  const [editing, setEditing] = useState(null);
  return (
    <section className="grid gap-5 xl:grid-cols-[1fr_1.2fr]">
      <div className="space-y-3">
        <button type="button" className="btn-primary" onClick={() => setEditing({})}><Plus className="mr-2 h-4 w-4" />Nieuwe ruimte</button>
        {rooms.map((room) => (
          <button key={room.id} type="button" className="card flex w-full items-center justify-between p-4 text-left" onClick={() => setEditing(room)}>
            <span><strong className="block text-gray-900 dark:text-gray-100">{room.name}</strong><span className="text-sm text-gray-500">{room.archived ? 'Gearchiveerd' : room.location}</span></span>
            <Pencil className="h-4 w-4" />
          </button>
        ))}
      </div>
      {editing ? <RoomForm room={editing.id ? editing : null} onClose={() => setEditing(null)} onSaved={async () => { await onSaved(); setEditing(null); }} /> : <EmptyState>Kies een ruimte om de instellingen te bewerken.</EmptyState>}
    </section>
  );
}

function RoomForm({ room, onClose, onSaved }) {
  const firstWindow = room?.opening_hours?.[0];
  const [values, setValues] = useState({
    name: room?.name || '', location: room?.location || '', description: room?.description || '', capacity: room?.capacity || 0,
    facilities: (room?.facilities || []).join(', '), booking_enabled: room?.booking_enabled ?? true,
    display_id: room?.display_id || '', presentation_controlled: room?.presentation_controlled ?? false,
    open_time: firstWindow?.start_time || '08:00', close_time: firstWindow?.end_time || '23:00',
    minimum_duration_minutes: room?.minimum_duration_minutes || 30, maximum_duration_minutes: room?.maximum_duration_minutes || 240,
    booking_interval_minutes: room?.booking_interval_minutes || 15, minimum_notice_minutes: room?.minimum_notice_minutes || 0,
    maximum_advance_days: room?.maximum_advance_days || 90, changeover_buffer_minutes: room?.changeover_buffer_minutes || 0,
    access_before_minutes: room?.access_before_minutes ?? 5, extension_increment_minutes: room?.extension_increment_minutes || 15,
    sort_order: room?.sort_order || 0, member_instructions: room?.member_instructions || '', archived: room?.archived ?? false,
  });
  const configQuery = useQuery({ queryKey: ['rooms', 'management-config'], queryFn: async () => (await prmApi.getRoomManagementConfig()).data });
  const mutation = useMutation({
    mutationFn: (payload) => room ? prmApi.updateRoom(room.id, payload) : prmApi.createRoom(payload),
    onSuccess: onSaved,
  });
  const update = (key, value) => setValues((current) => ({ ...current, [key]: value }));
  const submit = (event) => {
    event.preventDefault();
    mutation.mutate({
      ...values,
      display_id: values.display_id ? Number(values.display_id) : null,
      opening_hours: Array.from({ length: 7 }, (_, index) => ({ day: index + 1, start_time: values.open_time, end_time: values.close_time })),
    });
  };
  return (
    <form className="card space-y-4 p-5" onSubmit={submit}>
      <div className="flex items-center justify-between"><h2 className="text-lg font-semibold">{room ? room.name : 'Nieuwe ruimte'}</h2><button type="button" className="btn-tertiary p-2" onClick={onClose} aria-label="Sluiten"><X className="h-4 w-4" /></button></div>
      <div className="grid gap-3 sm:grid-cols-2">
        <TextField label="Naam" value={values.name} onChange={(value) => update('name', value)} required />
        <TextField label="Locatie" value={values.location} onChange={(value) => update('location', value)} />
        <NumberField label="Capaciteit" value={values.capacity} onChange={(value) => update('capacity', value)} min="0" />
        <TextField label="Faciliteiten, kommagescheiden" value={values.facilities} onChange={(value) => update('facilities', value)} />
        <TimeField label="Dagelijks open vanaf" value={values.open_time} onChange={(value) => update('open_time', value)} />
        <TimeField label="Dagelijks open tot" value={values.close_time} onChange={(value) => update('close_time', value)} />
        <NumberField label="Minimale duur (min.)" value={values.minimum_duration_minutes} onChange={(value) => update('minimum_duration_minutes', value)} min="5" />
        <NumberField label="Maximale duur (min.)" value={values.maximum_duration_minutes} onChange={(value) => update('maximum_duration_minutes', value)} min="5" />
        <NumberField label="Boekingsinterval (min.)" value={values.booking_interval_minutes} onChange={(value) => update('booking_interval_minutes', value)} min="5" />
        <NumberField label="Minimale voorbereiding (min.)" value={values.minimum_notice_minutes} onChange={(value) => update('minimum_notice_minutes', value)} min="0" />
        <NumberField label="Maximaal vooruit (dagen)" value={values.maximum_advance_days} onChange={(value) => update('maximum_advance_days', value)} min="1" />
        <NumberField label="Wisseltijd (min.)" value={values.changeover_buffer_minutes} onChange={(value) => update('changeover_buffer_minutes', value)} min="0" />
        <NumberField label="Schermtoegang vooraf (min.)" value={values.access_before_minutes} onChange={(value) => update('access_before_minutes', value)} min="0" />
        <NumberField label="Verlengstap (min.)" value={values.extension_increment_minutes} onChange={(value) => update('extension_increment_minutes', value)} min="5" />
        <NumberField label="Sorteervolgorde" value={values.sort_order} onChange={(value) => update('sort_order', value)} />
        <label className="block"><span className="mb-1 block text-sm font-medium">Club TV-scherm</span><select className="input w-full" value={values.display_id} onChange={(event) => update('display_id', event.target.value)}><option value="">Geen scherm</option>{(configQuery.data?.displays || []).map((display) => <option key={display.id} value={display.id}>{display.name}</option>)}</select></label>
      </div>
      <label className="block"><span className="mb-1 block text-sm font-medium">Omschrijving</span><textarea className="input min-h-20 w-full" value={values.description} onChange={(event) => update('description', event.target.value)} /></label>
      <label className="block"><span className="mb-1 block text-sm font-medium">Instructies voor gebruikers</span><textarea className="input min-h-20 w-full" value={values.member_instructions} onChange={(event) => update('member_instructions', event.target.value)} /></label>
      <div className="space-y-2">
        <CheckField label="Reserveerbaar" checked={values.booking_enabled} onChange={(checked) => update('booking_enabled', checked)} />
        <CheckField label="Presentatietoegang door reserveringen laten bepalen" checked={values.presentation_controlled} onChange={(checked) => update('presentation_controlled', checked)} />
        <CheckField label="Ruimte archiveren" checked={values.archived} onChange={(checked) => update('archived', checked)} />
      </div>
      {mutation.error && <ErrorNotice error={mutation.error} />}
      <div className="flex justify-end"><button type="submit" className="btn-primary" disabled={mutation.isPending}>{mutation.isPending ? 'Opslaan…' : 'Ruimte opslaan'}</button></div>
    </form>
  );
}

function ActivityDialog({ booking, onClose }) {
  const query = useQuery({ queryKey: ['rooms', 'activity', booking.id], queryFn: async () => (await prmApi.getRoomBookingActivity(booking.id)).data });
  return (
    <Dialog title={`Geschiedenis · ${booking.room_name}`} onClose={onClose}>
      {query.isLoading ? <ContentLoadingSpinner /> : query.data?.length ? (
        <ol className="space-y-3">
          {query.data.map((entry) => <li key={entry.id} className="border-l-2 border-emerald-500 pl-3"><strong className="block text-sm">{entry.action}</strong><span className="text-sm text-gray-500">{entry.actor_name} · {formatBookingTime(entry.created_at)}</span>{entry.reason && <p className="mt-1 text-sm">{entry.reason}</p>}</li>)}
        </ol>
      ) : <EmptyState>Nog geen geschiedenis.</EmptyState>}
    </Dialog>
  );
}

function Dialog({ title, onClose, children }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
      <section className="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-5 shadow-2xl dark:bg-gray-900" role="dialog" aria-modal="true" aria-label={title}>
        <div className="mb-4 flex items-center justify-between gap-3"><h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100">{title}</h2><button type="button" className="btn-tertiary p-2" onClick={onClose} aria-label="Sluiten"><X className="h-4 w-4" /></button></div>
        {children}
      </section>
    </div>
  );
}

function TextField({ label, value, onChange, required = false }) {
  return <label className="block"><span className="mb-1 block text-sm font-medium">{label}</span><input className="input w-full" value={value} onChange={(event) => onChange(event.target.value)} required={required} /></label>;
}

function NumberField({ label, value, onChange, min }) {
  return <label className="block"><span className="mb-1 block text-sm font-medium">{label}</span><input type="number" className="input w-full" value={value} min={min} onChange={(event) => onChange(Number(event.target.value))} /></label>;
}

function TimeField({ label, value, onChange }) {
  return <label className="block"><span className="mb-1 block text-sm font-medium">{label}</span><input type="time" className="input w-full" value={value} onChange={(event) => onChange(event.target.value)} required /></label>;
}

function CheckField({ label, checked, onChange }) {
  return <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} />{label}</label>;
}

function EmptyState({ children }) {
  return <div className="card p-8 text-center text-gray-500 dark:text-gray-400"><CalendarDays className="mx-auto mb-3 h-8 w-8 opacity-60" />{children}</div>;
}

function ErrorNotice({ error }) {
  return <div className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{error?.response?.data?.message || error?.message || 'Er ging iets mis.'}</div>;
}

function nextAvailableLabel(room, bookings, date) {
  const day = new Date(`${date}T12:00:00`).getDay() || 7;
  const window = room.opening_hours.find((item) => Number(item.day) === day);
  if (!window) return 'Niet geopend op deze dag.';
  const opening = new Date(`${date}T${window.start_time}:00`);
  const closing = new Date(`${date}T${window.end_time}:00`);
  const now = new Date();
  let cursor = localDateValue(now) === date && now > opening ? now : opening;
  const interval = room.booking_interval_minutes || 15;
  cursor.setMinutes(Math.ceil(cursor.getMinutes() / interval) * interval, 0, 0);
  const duration = room.minimum_duration_minutes * 60000;
  const buffer = room.changeover_buffer_minutes * 60000;
  for (const booking of [...bookings].sort((left, right) => new Date(left.start_datetime) - new Date(right.start_datetime))) {
    const start = new Date(new Date(booking.start_datetime).getTime() - buffer);
    const end = new Date(new Date(booking.end_datetime).getTime() + buffer);
    if (start - cursor >= duration) break;
    if (end > cursor) cursor = end;
  }
  if (cursor.getTime() + duration > closing.getTime()) return 'Geen vrije periode meer.';
  return `Eerst vrij om ${new Intl.DateTimeFormat('nl-NL', { hour: '2-digit', minute: '2-digit' }).format(cursor)}.`;
}
