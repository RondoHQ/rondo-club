#!/usr/bin/env python3
"""Generate the reproducible, fictional Rondo showcase; never reads a live site."""
import json
import hashlib
import subprocess
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
records, terms, comments = [], [], []

def ref(value):
    return {'$ref': value}

def day(value, fmt='Y-m-d'):
    return {'$date': value, 'format': fmt}

def dt(value):
    return day(value, 'c')

def add(key, post_type, title, fields=None, status='publish', meta=None, **extra):
    record = {'_ref': key, 'post_type': post_type, 'title': title, 'status': status}
    if fields:
        record['fields'] = fields
    if meta:
        record['post_meta'] = meta
    record.update(extra)
    records.append(record)
    return record

def term(key, taxonomy, name, slug, **extra):
    terms.append({'_ref': key, 'taxonomy': taxonomy, 'name': name, 'slug': slug, **extra})

def job(entity, title, start='-2 years', current=True):
    return {'team_id': ref(entity), 'job_title': title, 'start_date': day(start), 'is_current': current,
            'entity_type': 'commissie' if entity.startswith('commissie:') else 'team'}

term('relationship_type:parent', 'relationship_type', 'Ouder', 'parent')
term('relationship_type:child', 'relationship_type', 'Kind', 'child')
term('relationship_type:partner', 'relationship_type', 'Partner', 'partner')
term('seizoen:current', 'seizoen', '{season}', '{season}')
term('clothing_category:match', 'clothing_category', 'Wedstrijdkleding', 'wedstrijdkleding')
term('clothing_category:staff', 'clothing_category', 'Kaderkleding', 'kaderkleding')

team_specs = [('senior1', 'SV Voorbeeld 1', 26), ('senior2', 'SV Voorbeeld 2', 29),
              ('women1', 'SV Voorbeeld VR1', 24), ('o23', 'SV Voorbeeld O23-1', 21),
              ('jo19', 'SV Voorbeeld JO19-1', 17), ('mo17', 'SV Voorbeeld MO17-1', 15),
              ('jo15', 'SV Voorbeeld JO15-1', 13), ('jo13', 'SV Voorbeeld JO13-1', 11),
              ('jo11', 'SV Voorbeeld JO11-1', 9), ('jo9', 'SV Voorbeeld JO9-1', 7),
              ('g1', 'SV Voorbeeld G1', 32), ('walking', 'SV Voorbeeld Walking Football', 62)]
for slug, name, age in team_specs:
    matches = []
    for n, offset in enumerate([-14, -7, 3, 10, 17]):
        matches.append({'id': f'showcase-{slug}-{n}', 'starts_at': dt(f'{offset:+d} days 14:00'),
                        'date': day(f'{offset:+d} days'), 'time': '14:00', 'home_team': name,
                        'away_team': 'VV Horizon' if n % 2 else 'FC Polder', 'location': 'Sportpark De Voorbeeldvelden',
                        'pitch': 'Veld 1', 'status': 'Afgelast' if n == 4 else 'Vastgesteld', 'cancelled': n == 4,
                        'result': '3 - 1' if offset < 0 else '', 'time_known': True, 'competition': 'Competitie',
                        'duration_minutes': 105 if age >= 17 else 75, 'sequence': 0, 'modified_at': dt('-1 day')})
    add('team:' + slug, 'team', name, {'activiteit': 'Veldvoetbal', 'publicteamid': 'showcase-' + slug,
        'gender': 'female' if slug in ['women1', 'mo17'] else 'male', 'website': 'https://club.example/teams/' + slug,
        'contact_info': [{'contact_type': 'email', 'contact_label': 'Teamcontact', 'contact_value': slug + '@club.example'}]},
        meta={'_rondo_team_matches_cache': {'season': '{season}', 'identity': 'showcase-' + slug + '|Veldvoetbal',
        'duration_version': 1, 'matched': True, 'matches': matches, 'matchdays': [], 'stale': False,
        'updated_at': dt('today'), 'retry_after': day('+1 year', 'U')}})

committee_specs = [('board', 'Bestuur'), ('youth', 'Jeugdcommissie'), ('bar', 'Kantine'),
                   ('cleaning', 'Schoonmaak'), ('grounds', 'Werkploeg terreinonderhoud'),
                   ('events', 'Activiteiten'), ('sponsors', 'Sponsorcommissie'), ('clothing', 'Kledingcommissie')]
for slug, name in committee_specs:
    add('commissie:' + slug, 'commissie', name, {'taakomschrijving': 'Samen zorgen voor een gastvrije en goed georganiseerde vereniging.',
        'lange_omschrijving': 'Fictieve commissie voor de Rondo-demo.', 'max_leden': 12, 'max_wachtlijst': 4,
        'uren_aantal': 4, 'uren_periode': 'maand', 'dagen_flexibel': 'In overleg'})

first_names = ['Anna', 'Bram', 'Cato', 'Daan', 'Elin', 'Finn', 'Gwen', 'Hugo', 'Iris', 'Jens', 'Kiki', 'Lars', 'Mila', 'Niek', 'Olivia', 'Pim']
last_names = ['Bos', 'Meijer', 'Bakker', 'Visser', 'Jansen', 'Smit', 'Mulder', 'Dekker', 'Vos', 'Peters', 'Kuiper', 'Koster']
for t, (slug, name, age) in enumerate(team_specs):
    for n in range(16):
        i = t * 16 + n
        first, last = first_names[n], last_names[t]
        roles = [job('team:' + slug, 'Speler')]
        volunteer = n < 3
        if volunteer:
            roles.append(job('commissie:bar', 'Vrijwilliger'))
        fields = {'first_name': first, 'last_name': last, 'gender': 'female' if slug in ['women1', 'mo17'] else ('female' if n % 3 == 0 else 'male'),
                  'person_type': 'member', 'type_lid': 'Bondslid', 'knvb_id': f'DEMO{i + 1:05d}',
                  'birthdate': day(f'-{age} years'), 'lid_sinds': day('-2 years'),
                  'leeftijdsgroep': 'Senioren' if age >= 18 else f'Onder {age + 2}', 'spelactiviteit': 'Veldvoetbal',
                  'email_1': f'lid{i + 1}@club.example', 'work_history': roles, 'huidig_vrijwilliger': volunteer,
                  'datum_vog': day('-1 year') if volunteer and n != 2 else None,
                  'datum_iva': day('-6 months') if volunteer else None, 'iva_approved': volunteer,
                  'addresses': [{'street_name': 'Voorbeeldlaan', 'house_number': str(i + 1), 'postal_code': '1234 AB',
                                 'city': 'Voorbeelddorp', 'country': 'Nederland', 'country_code': 'NL'}]}
        if i == 0:
            fields['work_history'] += [job('commissie:board', 'Secretaris'), job('commissie:events', 'Coördinator toernooien'), job('team:senior1', 'Trainer')]
            fields.update(vrijwilliger_sinds=day('-5 years'), sponsor_pass_variant='businessclub', is_sponsor=True)
        if i == 1:
            fields.update(financiele_blokkade=True)
        if i == 2:
            fields.update(datum_vog=day('-4 years'))
        if i == 3:
            fields.update(vrijgesteld_handmatig=True, vrijstelling_reden='Mantelzorg (fictief)', vrijstelling_seizoen='{season}')
        if i == 4:
            fields.update(betaalde_vrijwilliger=True, vergoeding_reden='knvb-trainer', vergoeding_tot=day('+6 months'))
        if i == 5:
            fields.update(birthdate=day('-35 years'), lid_sinds=day('-25 years'))
        if i == 6:
            fields.update(lid_sinds=day('-2 days'), wacht_op_overschrijving=True)
        add(f'person:p{i + 1:03d}', 'person', first + ' ' + last, fields)

# Deliberate households, former members, new volunteers and sponsor contacts.
for i in range(12):
    children = [112, 128, 144] if i == 0 else [112 + i]
    parent = 'person:parent' + str(i + 1)
    address = {'street_name': 'Voorbeeldlaan', 'house_number': str(children[0] + 1), 'postal_code': '1234 AB',
               'city': 'Voorbeelddorp', 'country': 'Nederland', 'country_code': 'NL'}
    add(parent, 'person', first_names[(i + 3) % 16] + ' Familie' + str(i + 1),
        {'first_name': first_names[(i + 3) % 16], 'last_name': 'Familie' + str(i + 1), 'person_type': 'member',
         'type_lid': 'Ouder', 'birthdate': day('-40 years'), 'isparent': True, 'email_1': f'ouder{i + 1}@club.example',
         'addresses': [dict(address)], 'relationships': [{'related_person_id': ref(f'person:p{child + 1:03d}'), 'relationship_type_id': ref('relationship_type:child')} for child in children]})
    for child in children:
        child_record = next(r for r in records if r['_ref'] == f'person:p{child + 1:03d}')
        child_record['fields']['addresses'] = [dict(address)]
        child_record['fields']['relationships'] = [{'related_person_id': ref(parent), 'relationship_type_id': ref('relationship_type:parent')}]
for i in range(6):
    add('person:former' + str(i), 'person', 'Oudlid Voorbeeld' + str(i + 1),
        {'first_name': 'Oudlid', 'last_name': 'Voorbeeld' + str(i + 1), 'type_lid': 'Oud bondslid', 'former_member': True,
         'lid_sinds': day('-10 years'), 'lid_tot': day('-1 year'), 'birthdate': day('-45 years'), 'email_1': f'oudlid{i + 1}@club.example',
         'work_history': [{**job('team:senior2', 'Speler', '-10 years', False), 'end_date': day('-1 year')}]})
for i in range(4):
    add('person:contact' + str(i), 'person', 'Sponsor Contact' + str(i + 1),
        {'first_name': 'Sponsor', 'last_name': 'Contact' + str(i + 1), 'person_type': 'contact',
         'email_1': f'sponsor{i + 1}@bedrijf.example', 'company_name': ['Polder Fietsen', 'Horizon Bouw', 'Voorbeeld Bakkerij', 'Groenveld Tuinen'][i]})

for i, slug in enumerate(['bar', 'cleaning', 'grounds']):
    add('dienst_type:' + slug, 'dienst_type', ['Kantinedienst', 'Schoonmaak', 'Terreinonderhoud'][i],
        {'description': 'Fictieve vrijwilligerstaak; kies een dienst en bekijk de instructies.', 'color': ['#2563eb', '#16a34a', '#ca8a04'][i],
         'default_capacity': 2 if i == 0 else 4, 'vog_required': True, 'iva_required': i == 0, 'sleutel_involved': i == 2})
    add('shift_template:' + slug, 'shift_template', 'Wekelijkse ' + slug,
        {'dienst_type_id': ref('dienst_type:' + slug), 'day_of_week': 6, 'start_time': '09:00', 'end_time': '12:00', 'capacity': 2,
         'active_from': day('-1 month'), 'active_until': day('+2 months')})
    add('taakuitleg:' + slug, 'taakuitleg', 'Zo werkt ' + slug,
        {'dienst_types': [ref('dienst_type:' + slug)]}, content='<h2>Voor je begint</h2><p>Meld je bij de dienstcoördinator. Dit is een fictieve taakuitleg.</p>')
for i, offset in enumerate([-14, -7, -2, 1, 3, 7, 10, 14, 17, 21, 24, 28]):
    status = 'voltooid' if offset < 0 else ('vol' if i == 5 else ('geannuleerd' if i == 8 else 'open'))
    assigned = [ref('person:p001'), ref('person:p002')] if status in ['vol', 'voltooid'] else ([ref('person:p003')] if i % 2 else [])
    meta = {'_shift_attendance_log': {'person_id': ref('person:p004'), 'action': 'added', 'user_id': {'$user': 'demo'}, 'recorded_at': day('-1 day', 'U')}} if i == 2 else None
    if i == 2:
        assigned.append(ref('person:p004'))
    add('dienst_shift:shift' + str(i), 'dienst_shift', 'Kantine ' + str(i + 1),
        {'dienst_type_id': ref('dienst_type:bar'), 'template_id': ref('shift_template:bar'), 'start_datetime': dt(f'{offset:+d} days 09:00'),
         'end_datetime': dt(f'{offset:+d} days 12:00'), 'capacity': 2, 'status': status, 'assigned_persons': assigned,
         'notes': 'Achteraf geregistreerde extra hulp' if i == 2 else 'Fictieve demo-dienst'}, meta=meta)

for i in range(4):
    add('rondo_sponsor:s' + str(i), 'rondo_sponsor', ['Polder Fietsen', 'Horizon Bouw', 'Voorbeeld Bakkerij', 'Groenveld Tuinen'][i],
        {'sponsor_type': 'organization', 'sponsor_role': 'businessclub' if i < 2 else 'awc_sponsor', 'website': 'https://bedrijf.example',
         'address_city': 'Voorbeelddorp', 'address_street_name': 'Ondernemersweg', 'address_house_number': str(i + 1),
         'contacts': [{'person_id': ref('person:contact' + str(i)), 'contact_role': 'Eigenaar', 'is_primary': True,
                       'is_primary_pass': True, 'receives_pass': True}], 'club_tv_priority': min(i + 1, 3)})

for i in range(8):
    add('discipline_case:c' + str(i), 'discipline_case', 'Demo tuchtzaak ' + str(i + 1),
        {'person': ref(f'person:p{i + 2:03d}'), 'dossier_id': f'DEMO-TUCHT-{i + 1:03d}', 'match_date': day(f'-{i + 7} days'),
         'processing_date': day('-3 days'), 'home_team': ref('team:senior1'), 'away_team': None,
         'match_description': 'SV Voorbeeld 1 - FC Polder', 'charge_codes': 'DEMO', 'charge_description': 'Fictieve gele kaart',
         'sanction_description': 'Waarschuwing', 'administrative_fee': 12.5, 'is_charged': 'rondo' if i < 4 else ''},
        taxonomies={'seizoen': [ref('seizoen:current')]})
for i in range(16):
    state = ['draft', 'sent', 'overdue', 'paid'][i % 4]
    inv_type = 'discipline' if i < 4 else ('tournament' if i >= 14 else 'membership')
    amount = 12.5 if inv_type == 'discipline' else (60 if inv_type == 'tournament' else 240)
    fields = {'invoice_number': f'DEMO-{i + 1:04d}', 'invoice_type': inv_type, 'person': ref(f'person:p{i + 1:03d}'),
              'status': state, 'total_amount': amount, 'due_date': day('-7 days' if state == 'overdue' else '+14 days'),
              'line_items': [{'description': {'discipline': 'Fictieve tuchtbijdrage', 'membership': 'Contributie {season}', 'tournament': 'Voorbeeldtoernooi'}[inv_type], 'amount': amount}]}
    if state != 'draft':
        fields['sent_date'] = day('-14 days')
    meta = {'_invoice_season': '{season}'}
    if state == 'paid':
        meta.update(_manually_marked_paid_at=day('-3 days', 'Y-m-d H:i:s'), _manually_marked_paid_by={'$user': 'demo'})
    if i in [9, 10]:
        count = 3 if i == 9 else 8
        meta.update(_installment_plan='quarterly_3' if count == 3 else 'monthly_8', _installment_count=count)
        for n in range(1, count + 1):
            meta.update({f'_installment_{n}_amount': amount / count, f'_installment_{n}_admin_fee': 0,
                         f'_installment_{n}_status': 'betaald' if n == 1 else 'pending',
                         f'_installment_{n}_due_date': day(f'+{n * 30} days')})
    add('rondo_invoice:i' + str(i), 'rondo_invoice', f'Factuur DEMO-{i + 1:04d}', fields, status='rondo_' + state, meta=meta)
for i, title in enumerate(['Nieuwe trainer bellen', 'VOG opvolgen', 'Kleding retour controleren', 'Sponsoravond voorbereiden', 'Teamindeling afronden', 'Contributie bespreken']):
    add('rondo_todo:t' + str(i), 'rondo_todo', title,
        {'related_persons': [ref(f'person:p{i + 1:03d}')], 'due_date': day('-2 days' if i == 0 else f'+{i + 1} days'), 'notes': 'Fictieve taak voor de demo.'},
        status=['rondo_open', 'rondo_awaiting', 'rondo_completed'][i % 3])
for i in range(24):
    comments.append({'post': ref(f'person:p{i + 1:03d}'), 'type': 'rondo_note' if i % 2 else 'rondo_activity',
                     'content': 'Kennismakingsgesprek gevoerd; voorkeur voor zaterdag besproken.' if i % 2 else 'Fictieve clubactiviteit: geholpen bij de open dag.',
                     'date': day(f'-{i + 1} days 10:00', 'Y-m-d H:i:s')})
for i, state in enumerate(['new', 'in_progress', 'resolved']):
    add('rondo_feedback:f' + str(i), 'rondo_feedback', ['Idee: gezamenlijke ouderavond', 'Vraag over teamkalender', 'Kledingmaat aangepast'][i],
        {'feedback_type': 'feature_request' if i == 0 else 'bug', 'status': state, 'priority': 'medium', 'use_case': 'Fictieve demo-feedback', 'url_context': '/teams'},
        content='Voorbeeld van feedback die binnen de club wordt opgevolgd.')

pitches = [{'id': 'pitch1', 'name': 'Veld 1'}, {'id': 'pitch2', 'name': 'Veld 2'}]
groups = [{'id': 'youth', 'name': 'Jeugd', 'color': '#2563eb'}, {'id': 'senior', 'name': 'Senioren', 'color': '#16a34a'}]
training_blocks = [{'block_id': 'block' + str(i), 'label': name, 'team_ids': [ref('team:' + slug)],
                    'age_group_id': 'senior' if age >= 18 else 'youth', 'pitch_id': 'pitch' + str(i % 2 + 1),
                    'day': i % 5 + 1, 'start': '19:30' if age >= 18 else '18:00', 'duration': 90, 'size': 2, 'offset': 2 if i >= 10 else 0}
                   for i, (slug, name, age) in enumerate(team_specs)]
add('rondo_training:active', 'rondo_training', 'Trainingsschema {season}', {'season': '{season}', 'revision': 1, 'blocks': training_blocks}, status='private')
add('rondo_training:draft', 'rondo_training', 'Voorstel winterindeling', {'season': '{season}', 'revision': 1, 'blocks': training_blocks[:6]}, status='private')
add('rondo_park_closure:maintenance', 'rondo_park_closure', 'Onderhoud kunstgrasveld', {'starts_at': day('+18 days'), 'ends_at': day('+18 days')})
for i, title in enumerate(['Bestuurskamer', 'Teamruimte', 'Clubhuis']):
    add('rondo_room:r' + str(i), 'rondo_room', title,
        {'location': 'Clubgebouw', 'description': 'Fictieve ruimte voor teamoverleg en clubactiviteiten.', 'capacity': [12, 20, 80][i],
         'booking_enabled': True, 'booking_interval_minutes': 30, 'minimum_duration_minutes': 30, 'maximum_duration_minutes': 180,
         'minimum_notice_minutes': 30, 'maximum_advance_days': 90, 'changeover_buffer_minutes': 15, 'access_before_minutes': 15,
         'member_instructions': 'Laat de ruimte netjes achter.', 'opening_hours': [{'day': n, 'start_time': '08:00', 'end_time': '23:00'} for n in range(1, 8)], 'facilities': [{'name': 'Wifi'}, {'name': 'Beamer'}]})
for i in range(6):
    add('rondo_room_booking:b' + str(i), 'rondo_room_booking', ['Teamoverleg', 'Jeugdcommissie', 'Sponsorontvangst'][i % 3],
        {'room_id': ref('rondo_room:r' + str(i % 3)), 'holder_person_id': ref('person:p001'), 'holder_user_id': {'$user': 'demo'},
         'created_by_user_id': {'$user': 'demo'}, 'start_datetime': dt(f'+{i + 1} days 19:00'), 'end_datetime': dt(f'+{i + 1} days 20:00'),
         'purpose': 'Fictieve reservering', 'status': 'cancelled' if i == 5 else 'confirmed', 'booking_type': 'member_reservation',
         'booking_context_type': 'team', 'eligibility_team_id': ref('team:senior1'), 'context_label_snapshot': 'SV Voorbeeld 1'}, status='private')

for i, state in enumerate(['draft', 'open', 'closed']):
    add('rondo_tournament:t' + str(i), 'rondo_tournament', ['Voorjaarstoernooi', 'Horizon Jeugdcup', 'Polder Zomercup'][i],
        {'description': 'Fictief toernooi voor jeugdteams.', 'organizer': 'SV Voorbeeld', 'location': 'Sportpark De Voorbeeldvelden',
         'lifecycle_status': state, 'external_status': 'not_processed' if i < 2 else 'confirmed', 'version': 1,
         'internal_deadline': day('+14 days'), 'external_deadline': day('+21 days'), 'payment_deadline': day('+28 days'),
         'target_team_ids': [ref('team:jo9'), ref('team:jo11'), ref('team:jo13')], 'pricing_rules': [{'min_age': 8, 'max_age': 13, 'game_format': '6x6', 'amount': 60}],
         'schedule': [{'age_group': 'JO11', 'location': 'Veld 1', 'start_datetime': dt('+42 days 09:00')}],
         'created_by_user_id': {'$user': 'demo'}}, status='draft' if i == 0 else 'publish')
for i, state in enumerate(['open', 'submitted', 'submitted']):
    team_slug = ['jo9', 'jo11', 'jo13'][i]
    add('rondo_tourn_entry:e' + str(i), 'rondo_tourn_entry', 'Deelname JO11 ' + str(i + 1),
        {'tournament_id': ref('rondo_tournament:t1'), 'team_id': ref('team:' + team_slug), 'team_name_snapshot': 'SV Voorbeeld ' + team_slug.upper() + '-1',
         'age_group_snapshot': 'JO11', 'registration_status': state, 'payment_state': ['not_applicable', 'error', 'paid'][i],
         'player_count': 8 if i else 0, 'registered_team_count': 1 if i else 0, 'price_per_team': 60, 'total_amount': 60 if i else 0, 'version': 1,
         'contact_person_id': ref('person:p001'), 'contact_name': 'Anna Bos', 'contact_email': 'lid1@club.example',
         'invoice_id': ref('rondo_invoice:i' + str(13 + i)) if i else None,
         'assignment_snapshot': [{'person_id': ref('person:p001'), 'user_id': {'$user': 'demo'}, 'name': 'Anna Bos', 'email': 'lid1@club.example', 'role': 'Trainer'}],
         'draft_team_entries': [{'sequence': 1, 'player_count': 8}], 'submitted_team_entries': [] if i == 0 else [{'sequence': 1, 'player_count': 8}]}, status='publish', meta={'_tournament_assigned_person_{{person:p001}}': 1})

add('rondo_comm_series:monthly', 'rondo_comm_series', 'Maandelijkse clubnieuwsbrief',
    {'recurrence': 'monthly', 'start_date': day('-3 months'), 'series_status': 'active', 'channels': [{'channel_id': 'newsletter'}],
     'description': 'Jeugd, vrijwilligers en clubnieuws', 'assignee_id': {'$user': 'demo'}})
for i, state in enumerate(['concept', 'preparing', 'ready', 'sent', 'cancelled']):
    add('rondo_comm_item:c' + str(i), 'rondo_comm_item', ['Open dag', 'Nieuwe teamindeling', 'Vrijwilligersavond', 'Terugblik clubweekend', 'Verplaatste sponsorborrel'][i],
        {'status': state, 'planned_date': day('-7 days' if i == 3 else f'+{i + 2} days'), 'actual_date': day('-7 days') if i == 3 else None,
         'channels': [{'channel_id': 'newsletter'}, {'channel_id': 'website'}], 'assignee_id': {'$user': 'demo'},
         'series_id': ref('rondo_comm_series:monthly') if i == 3 else None,
         'newsletter_subject': 'Nieuws van SV Voorbeeld', 'newsletter_heading': 'Samen maken we de club',
         'newsletter_preheader': 'Fictief clubnieuws voor de demo', 'newsletter_body': '<p>De open dag biedt trainingen, spelletjes en een rondleiding voor nieuwe leden.</p>',
         'description': 'Fictief communicatie-item.'})

for i, kind in enumerate(['announcement', 'sponsor', 'matches', 'results', 'fallback']):
    add('rondo_signage_item:s' + str(i), 'rondo_signage_item', ['Welkom bij SV Voorbeeld', 'Polder Fietsen', 'Wedstrijden vandaag', 'Uitslagen', 'Samen maken we de club'][i],
        {'content_type': kind, 'body': 'Fictieve clubinformatie voor bezoekers en leden.', 'enabled': True, 'duration_seconds': 15,
         'priority': 10, 'use_club_colors': True, 'sponsor_id': ref('rondo_sponsor:s0') if kind == 'sponsor' else None})
add('rondo_signage_list:clubhouse', 'rondo_signage_list', 'Clubhuis standaard',
    {'enabled': True, 'days_of_week': ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], 'start_time': '08:00', 'end_time': '23:00',
     'items': [{'item_id': ref('rondo_signage_item:s' + str(i)), 'duration_seconds': 15, 'weight': 1} for i in range(5)],
     'fallback_item_id': ref('rondo_signage_item:s4')})
for i, name in enumerate(['Clubhuis', 'Entree']):
    add('rondo_display:d' + str(i), 'rondo_display', name,
        {'location': name, 'pairing_status': 'approved', 'assigned_playlist_id': ref('rondo_signage_list:clubhouse'),
         'device_id': 'showcase-display-' + str(i), 'display_timezone': 'Europe/Amsterdam', 'update_channel': 'off',
         'wake_time': '08:00', 'sleep_time': '23:00', 'last_error': 'Fictief scherm; geen fysieke player gekoppeld.'})
for i, offset in enumerate([-7, 3, 10]):
    add('rondo_access_event:m' + str(i), 'rondo_access_event', 'SV Voorbeeld 1 - FC Polder',
        {'home_team': 'SV Voorbeeld 1', 'away_team': 'FC Polder', 'source_id': 'showcase-entry-' + str(i),
         'starts_at': dt(f'{offset:+d} days 14:00'), 'location': 'Sportpark De Voorbeeldvelden', 'pitch': 'Veld 1', 'cancelled': False})
add('rondo_guest_pass:g1', 'rondo_guest_pass', 'Fictieve gast: Noor Voorbeeld',
    {'host_person_id': ref('person:p001'), 'guest_name': 'Noor Voorbeeld', 'slot_number': 1, 'pass_status': 'active', 'pass_version': 1, 'claimed_at': dt('-8 days')})
for i in range(6):
    add('rondo_admission:a' + str(i), 'rondo_admission', 'Fictieve toegang ' + str(i + 1),
        {'event_id': ref('rondo_access_event:m0'), 'host_person_id': ref(f'person:p{i + 1:03d}'),
         'pass_type': 'bondslid', 'scanned_at': dt('-7 days 13:45')})

for i, name in enumerate(['Wedstrijdshirt', 'Trainingspak', 'Coachjas']):
    add('rondo_clothing_item:c' + str(i), 'rondo_clothing_item', name,
        meta={'_clothing_brand': 'Voorbeeld Sport', '_clothing_color': 'Blauw / wit', '_clothing_season': '{season}',
              '_clothing_unit_cost': [25, 55, 75][i], '_clothing_deposit_amount': [20, 40, 50][i],
              '_clothing_active': '1', '_clothing_available_sizes': ['128', '140', '152', 'S', 'M', 'L', 'XL']},
        taxonomies={'clothing_category': [ref('clothing_category:staff' if i == 2 else 'clothing_category:match')]})
for i in range(12):
    add('rondo_clothing_txn:x' + str(i), 'rondo_clothing_txn', 'Kledinguitgifte ' + str(i + 1),
        meta={'_clothing_person_id': ref(f'person:p{i + 1:03d}'), '_clothing_item_id': ref('rondo_clothing_item:c' + str(i % 3)),
              '_clothing_size': ['M', 'L', 'S'][i % 3], '_clothing_condition': 'good', '_clothing_date': day('-14 days'),
              '_clothing_handled_by': {'$user': 'demo'}, '_clothing_in_or_out': 'in' if i == 11 else 'out',
              '_clothing_deposit_paid': '1', '_clothing_deposit_returned': '1' if i == 11 else '0',
              '_clothing_season': '{season}', '_clothing_notes': 'Fictieve kledingtransactie'})

# Twelve reports contain synthetic daily, hourly and product data, not copies of live reports.
for i in range(14):
    gross = 450 + i * 35
    omzet = [{'section': 'totaal', 'label': 'Omzet (excl. no-sale)', 'bedrag': gross, 'netto': round(gross / 1.09, 2),
              'hoog': 0, 'laag': round(gross - gross / 1.09, 2), 'geen_btw': 0, 'transacties': 80 + i}]
    omzet += [{'section': 'uur', 'label': f'{h:02d}:00 - {h + 1:02d}:00', 'bedrag': round(gross * w, 2),
               'netto': round(gross * w / 1.09, 2), 'hoog': 0, 'laag': round(gross * w * 9 / 109, 2), 'geen_btw': 0, 'transacties': 10}
              for h, w in zip(range(10, 16), [.1, .15, .25, .25, .15, .1])]
    weights = [.3, .2, .25, .25]
    products = []
    revenue = []
    transactions = []
    for j, name in enumerate(['Koffie', 'Thee', 'Cola', 'Broodje kaas']):
        cents = round(gross * weights[j] * 100)
        bruto = cents / 100
        products.append({'product': name, 'aantal': 30 + j * 5, 'bruto': bruto, 'netto': round(bruto / 1.09, 2),
                         'btw': round(bruto * 9 / 109, 2), 'btw_groep': 'Laag'})
        revenue.append({'product': name, 'cashCents': cents, 'businessclubCents': 0})
        transactions.append({'id': f'showcase-{i}-{j}', 'kind': 'sale', 'localTime': day(f'-{i + 1} days {j + 11:02d}:00', 'Y-m-d H:i'),
                             'products': [{'name': name, 'cashCents': cents, 'businessclubCents': 0}]})
    parsed = {'club': 'SV Voorbeeld', 'period_start': day(f'-{i + 1} days 06:00', 'Y-m-d H:i'),
              'period_end': day(f'-{i} days 06:00', 'Y-m-d H:i'), 'omzet': omzet, 'producten': products,
              'producten_totaal': {'bruto': gross, 'aantal': sum(p['aantal'] for p in products)},
              'source': {'coverage_end': day(f'-{i} days 06:00', 'Y-m-d H:i')}, 'no_sale_transactions': [],
              'product_revenue': {'version': 1, 'products': revenue}, 'activity': {'version': 1, 'transactions': transactions}}
    add('rondo_twelve_report:r' + str(i), 'rondo_twelve_report', 'Fictief kantinerapport ' + str(i + 1),
        meta={'_twelve_period_start': day(f'-{i + 1} days 06:00', 'Y-m-d H:i:s'), '_twelve_period_end': day(f'-{i} days 06:00', 'Y-m-d H:i:s'),
              '_twelve_message_id': 'showcase-report-' + str(i), '_twelve_report_data': {'$json': parsed}, '_twelve_total_gross': gross})
for i in range(3):
    add('rondo_purchase:p' + str(i), 'rondo_purchase', 'Fictieve inkoopfactuur ' + str(i + 1),
        {'invoice_number': 'DEMO-INKOOP-' + str(i + 1), 'supplier': 'Voorbeeld Groothandel', 'invoice_date': day(f'-{i * 7 + 1} days'),
         'vat_amount': 9, 'total_amount': 109, 'revision': 1,
         'lines': [{'article': 'DEMO-KOFFIE', 'description': 'Koffiebonen', 'amount': 100, 'deposit': 0, 'vat_rate': 9,
                    'packs': 10, 'pack_content': '1 kg', 'units_per_pack': 1, 'quantity': 10, 'unit': 'kg', 'category': 'Dranken', 'note': 'Fictieve factuur'}]})
for i, name in enumerate(['Koffie', 'Thee', 'Cola', 'Broodje kaas']):
    add('rondo_kassa_product:p' + str(i), 'rondo_kassa_product', name,
        {'twelve_id': str(i + 1), 'product_name': name, 'active': True, 'cost_status': ['ready', 'portion', 'mapping', 'recipe'][i],
         'cost_note': 'Fictieve kostprijs', 'revision': 1, 'ingredients': [{'article': 'DEMO-KOFFIE', 'quantity': .008, 'unit': 'kg'}] if i == 0 else [],
         'sale_prices': [{'effective_date': day('-1 month'), 'amount': [2.5, 2.25, 2.75, 3.5][i], 'vat_rate': 9, 'source': 'showcase'}]})
for i, offset in enumerate([-7, 3]):
    add('rondo_kantine_day:d' + str(i), 'rondo_kantine_day', 'Fictieve wedstrijddag ' + str(i + 1),
        meta={'_kantine_day': {'date': day(f'{offset:+d} days'), 'matches': [{'id': 'showcase-busy-' + str(i),
              'date': day(f'{offset:+d} days'), 'time': '14:00', 'home_team': 'SV Voorbeeld 1', 'away_team': 'FC Polder',
              'club_side': 'home', 'location': 'Sportpark De Voorbeeldvelden', 'status': 'Vastgesteld', 'cancelled': False,
              'result': '3 - 1' if offset < 0 else '', 'special': 'first', 'youth': False, 'activity': False, 'reason': None}],
              'complete': True, 'updated_at': dt('today')}})
for i in range(3):
    add('rondo_match_reg:m' + str(i), 'rondo_match_reg', 'Fictieve wedstrijdregistratie ' + str(i + 1),
        {'team_id': ref('team:senior1'), 'season': '{season}', 'phase': 'completed' if i < 2 else 'draft', 'version': 1,
         'source_match_id': 'showcase-senior1-' + str(i), 'played_on': day(f'-{i * 7 + 1} days'), 'opponent_name': 'FC Polder', 'home': True,
         'category': 'competitie', 'home_score': 3, 'away_score': 1,
         'selection': [{'person_id': ref(f'person:p{n + 1:03d}'), 'player_name': first_names[n] + ' Bos', 'participation': 'basis' if n < 11 else 'bank', 'guest': False} for n in range(16)]}, status='private')
add('rondo_onboard_round:new', 'rondo_onboard_round', 'Welkom: nieuw lid met overschrijving', status='private',
    parent=ref('person:p007'), meta={'_onboarding_key': 'showcase-new', '_onboarding_due': day('+1 day', 'U'), '_onboarding_recognized': day('today', 'U')})
add('rondo_profile_change:c1', 'rondo_profile_change', 'Fictieve contactwijziging', status='private',
    meta={'_rondo_profile_change_type': 'contact', '_rondo_profile_change_source': 'rondo', '_rondo_profile_change_person_ids': [ref('person:p001')],
          '_rondo_profile_change_changes': [{'person_id': ref('person:p001'), 'field': 'email_1', 'old_value': 'oud@club.example', 'new_value': 'lid1@club.example'}],
          '_rondo_profile_change_verified': '1', '_rondo_profile_change_sync_pending': []})

coverage = {
 'leden_en_relaties': ['person:p001', 'person:parent1', 'person:former0'],
 'teams_en_kader': ['team:jo11', 'team:senior1', 'commissie:board'],
 'vog_en_iva': ['person:p001', 'person:p003', 'person:p004'],
 'vrijwilligers_en_aanwezigheid': ['dienst_shift:shift2', 'dienst_shift:shift5', 'dienst_type:bar'],
 'contributie_en_termijnen': ['rondo_invoice:i9', 'rondo_invoice:i10', 'rondo_invoice:i11'],
 'tuchtzaken': ['discipline_case:c0', 'rondo_invoice:i0'], 'taken_en_feedback': ['rondo_todo:t0', 'rondo_feedback:f0'],
 'sponsoren': ['rondo_sponsor:s0'], 'trainingen': ['rondo_training:active'], 'ruimtes': ['rondo_room:r0', 'rondo_room_booking:b0'],
 'toernooien': ['rondo_tournament:t1', 'rondo_tourn_entry:e1'], 'communicatie': ['rondo_comm_item:c2', 'rondo_comm_series:monthly'],
 'club_tv': ['rondo_display:d0', 'rondo_signage_list:clubhouse'], 'toegangscontrole': ['rondo_access_event:m0', 'rondo_admission:a0'],
 'kleding': ['rondo_clothing_item:c0', 'rondo_clothing_txn:x0'], 'kantine': ['rondo_twelve_report:r0', 'rondo_purchase:p0', 'rondo_kassa_product:p0'],
 'wedstrijdregistratie': ['rondo_match_reg:m0'], 'onboarding_en_wijzigingen': ['person:p007', 'rondo_onboard_round:new', 'rondo_profile_change:c1'],
 'lidpassen_en_gastpassen': ['person:p001', 'rondo_guest_pass:g1'], 'jubilarissen': ['person:p006']}
settings = {'rondo_club_name': 'SV Voorbeeld', 'rondo_feature_toggles': {'rooms': 'on', 'clothing': 'on', 'narrowcasting': 'on'},
 'rondo_volunteer_pool_commissies': {'schoonmaak': ref('commissie:cleaning'), 'activiteiten': ref('commissie:events'), 'werkploeg': ref('commissie:grounds')},
 'rondo_volunteer_signup_info': 'Kies een fictieve dienst om de inschrijving te bekijken.',
 'rondo_player_roles': ['Speler'], 'rondo_excluded_roles': [], 'rondo_anniversary_milestones': [25, 40, 50, 60, 70],
 'rondo_vog_exempt_commissies': [], 'rondo_training_active': ref('rondo_training:active'),
 'rondo_training_settings': {'revision': 1, 'pitches': pitches, 'age_groups': groups,
    'teams': [{'team_id': ref('team:' + slug), 'age_group_id': 'senior' if age >= 18 else 'youth', 'duration': 90, 'size': 2} for slug, name, age in team_specs]},
 'rondo_communication_channels': [{'id': x, 'label': y, 'active': True} for x, y in [('newsletter', 'Nieuwsbrief'), ('website', 'Website'), ('whatsapp', 'WhatsApp')]],
 'rondo_newsletter_config': {'template': '', 'salutation': 'Beste leden,', 'channel_id': 'newsletter'},
 'rondo_narrowcasting_default_playlist_id': ref('rondo_signage_list:clubhouse'), 'rondo_guest_pass_team_id': ref('team:senior1'),
 'rondo_membership_fees_{season}': {'senior': {'label': 'Senioren', 'amount': 240, 'age_classes': ['Senioren'], 'is_youth': False, 'sort_order': 1},
    'youth': {'label': 'Jeugd', 'amount': 180, 'age_classes': ['Onder 9', 'Onder 11', 'Onder 13', 'Onder 15', 'Onder 17', 'Onder 19'], 'is_youth': True, 'sort_order': 2}},
 'rondo_family_discount_{season}': {'second_child_percent': 25, 'third_child_percent': 50},
 'rondo_twelve_product_groups': {hashlib.sha256(name.encode()).hexdigest(): 'food' if name == 'Broodje kaas' else 'non_food' for name in ['Koffie', 'Thee', 'Cola', 'Broodje kaas']},
 'rondo_match_compensation': {'teams': [{'team_id': ref('team:senior1'), 'scheme': 'awc1'}, {'team_id': ref('team:o23'), 'scheme': 'jo23'}], 'bank_code': '', 'retention_policy': 'Fictieve gegevens; geen echte uitbetaling.'}}
# Use the canonical registry to keep relative date markers in each field's wire format.
schema = json.loads(subprocess.check_output(['php', '-r', "$config = require $argv[1]; echo json_encode($config['contexts']);", str(ROOT / 'includes/config/field-registry.php')]))
def normalize_dates(fields, definitions):
    for name, value in fields.items():
        definition = definitions[name]
        if isinstance(value, dict) and '$date' in value:
            if definition['type'] == 'date_time_picker':
                value['format'] = 'c'
            elif definition['type'] == 'date_picker':
                value['format'] = 'Y-m-d'
        if definition['type'] == 'repeater' and isinstance(value, list):
            children = {child['canonical_name']: child for child in definition['sub_fields'].values()}
            for row in value:
                normalize_dates(row, children)
for record in records:
    if record.get('fields'):
        definitions = schema[record['post_type']]['fields']
        normalize_dates(record['fields'], definitions)
        for name in list(record['fields']):
            definition = definitions[name]
            if definition.get('read_only'):
                value = record['fields'].pop(name)
                if isinstance(value, dict) and '$date' in value:
                    value['format'] = definition.get('storage_format', 'Y-m-d H:i:s')
                assert definition['storage_name'] and definition['type'] != 'repeater'
                record.setdefault('post_meta', {})[definition['storage_name']] = value

fixture = {'meta': {'version': '2.0', 'source': 'fictional_showcase', 'name': 'SV Voorbeeld: alle modules',
                   'record_counts': dict(sorted(Counter(r['post_type'] for r in records).items()))},
           'terms': terms, 'records': records, 'comments': comments, 'settings': settings, 'coverage': coverage,
           'demo_account': {'roles': ['rondo_bestuur', 'rondo_kaderlijst'], 'capabilities': ['manage_training', 'narrowcasting', 'wedstrijdregistratie', 'wedstrijdzaken', 'feedback', 'commissies'], 'user_meta': {'rondo_linked_person_id': ref('person:p001'), 'rondo_approved': '1',
             '_rondo_match_teams': [ref('team:senior1')], 'rondo_newsletter_profile': {'name': 'Anna Bos', 'role': 'Secretaris',
             'from_name': 'SV Voorbeeld', 'from_email': 'club@club.example', 'reply_to': 'club@club.example', 'active': True}}}}
(ROOT / 'fixtures/demo-showcase.json').write_text(json.dumps(fixture, ensure_ascii=False, indent=2) + '\n')
print(f'{len(records)} records, {len(terms)} terms, {len(comments)} comments, {len(coverage)} feature scenarios')
