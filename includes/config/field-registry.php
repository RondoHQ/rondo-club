<?php
/**
 * Native Rondo field registry.
 *
 * This PHP configuration is the source of truth for static field definitions.
 */

return array (
  'computed_top_level' => 
  array (
    'commissie' => 
    array (
      0 => 'member_count',
    ),
    'person' => 
    array (
      0 => 'is_deceased',
      1 => 'birth_year',
    ),
    'team' => 
    array (
      0 => 'player_count',
      1 => 'staff_count',
    ),
  ),
  'contexts' => 
  array (
    'commissie' => 
    array (
      'fields' => 
      array (
        'contact_info' => 
        array (
          'button_label' => 'Add Contact Method',
          'canonical_name' => 'contact_info',
          'key' => 'field_commissie_contact_info',
          'label' => 'Contact Information',
          'layout' => 'table',
          'name' => 'contact_info',
          'storage_name' => 'contact_info',
          'sub_fields' => 
          array (
            'contact_label' => 
            array (
              'canonical_name' => 'contact_label',
              'key' => 'field_commissie_contact_label',
              'label' => 'Label',
              'name' => 'contact_label',
              'placeholder' => 'e.g., Chair, Secretary',
              'storage_name' => 'contact_label',
              'type' => 'text',
            ),
            'contact_type' => 
            array (
              'canonical_name' => 'contact_type',
              'choices' => 
              array (
                'address' => 'Address',
                'email' => 'Email',
                'other' => 'Other',
                'phone' => 'Phone',
              ),
              'key' => 'field_commissie_contact_type',
              'label' => 'Type',
              'name' => 'contact_type',
              'storage_name' => 'contact_type',
              'type' => 'select',
            ),
            'contact_value' => 
            array (
              'canonical_name' => 'contact_value',
              'key' => 'field_commissie_contact_value',
              'label' => 'Value',
              'name' => 'contact_value',
              'storage_name' => 'contact_value',
              'type' => 'text',
            ),
          ),
          'type' => 'repeater',
        ),
        'dagen_flexibel' => 
        array (
          'canonical_name' => 'dagen_flexibel',
          'instructions' => 'Gebeurt dit vooral op vaste dagen of is het flexibel?',
          'key' => 'field_commissie_dagen_flexibel',
          'label' => 'Dagen / flexibiliteit',
          'name' => 'dagen_flexibel',
          'placeholder' => 'Bijv. \'Voornamelijk op zaterdag\' of \'Flexibel\'',
          'storage_name' => 'dagen_flexibel',
          'type' => 'text',
        ),
        'lange_omschrijving' => 
        array (
          'canonical_name' => 'lange_omschrijving',
          'instructions' => 'Een uitgebreide omschrijving van deze commissie.',
          'key' => 'field_commissie_lange_omschrijving',
          'label' => 'Uitgebreide omschrijving',
          'name' => 'lange_omschrijving',
          'new_lines' => 'wpautop',
          'rows' => 5,
          'storage_name' => 'lange_omschrijving',
          'type' => 'textarea',
        ),
        'max_leden' => 
        array (
          'canonical_name' => 'max_leden',
          'instructions' => 'Maximaal aantal leden in deze commissie.',
          'key' => 'field_commissie_max_leden',
          'label' => 'Maximum aantal leden',
          'min' => 0,
          'name' => 'max_leden',
          'storage_name' => 'max_leden',
          'type' => 'number',
        ),
        'max_wachtlijst' => 
        array (
          'canonical_name' => 'max_wachtlijst',
          'instructions' => 'Maximaal aantal personen op de wachtlijst.',
          'key' => 'field_commissie_max_wachtlijst',
          'label' => 'Maximum aantal op wachtlijst',
          'min' => 0,
          'name' => 'max_wachtlijst',
          'storage_name' => 'max_wachtlijst',
          'type' => 'number',
        ),
        'taakomschrijving' => 
        array (
          'canonical_name' => 'taakomschrijving',
          'instructions' => 'Wat doet een lid van deze commissie?',
          'key' => 'field_commissie_taakomschrijving',
          'label' => 'Taakomschrijving',
          'name' => 'taakomschrijving',
          'new_lines' => 'wpautop',
          'rows' => 5,
          'storage_name' => 'taakomschrijving',
          'type' => 'textarea',
        ),
        'uren_aantal' => 
        array (
          'canonical_name' => 'uren_aantal',
          'instructions' => 'Geschatte tijdsinvestering voor een lid.',
          'key' => 'field_commissie_uren_aantal',
          'label' => 'Geschat aantal uren',
          'min' => 0,
          'name' => 'uren_aantal',
          'step' => 0.5,
          'storage_name' => 'uren_aantal',
          'type' => 'number',
        ),
        'uren_periode' => 
        array (
          'ajax' => 0,
          'allow_null' => 1,
          'canonical_name' => 'uren_periode',
          'choices' => 
          array (
            'maand' => 'Per maand',
            'week' => 'Per week',
          ),
          'default_value' => '',
          'instructions' => 'Per welke periode geldt het aantal uren?',
          'key' => 'field_commissie_uren_periode',
          'label' => 'Periode',
          'multiple' => 0,
          'name' => 'uren_periode',
          'placeholder' => '',
          'return_format' => 'value',
          'storage_name' => 'uren_periode',
          'type' => 'select',
          'ui' => 0,
        ),
        'website' => 
        array (
          'canonical_name' => 'website',
          'key' => 'field_commissie_website',
          'label' => 'Website',
          'name' => 'website',
          'storage_name' => 'website',
          'type' => 'url',
        ),
      ),
      'kind' => 'post',
    ),
    'dienst_shift' => 
    array (
      'fields' => 
      array (
        'assigned_persons' => 
        array (
          'canonical_name' => 'assigned_persons',
          'filters' => 
          array (
            0 => 'search',
          ),
          'instructions' => 'Personen die deze inschrijftaak uitvoeren.',
          'key' => 'field_dienst_shift_assigned_persons',
          'label' => 'Toegewezen vrijwilligers',
          'max' => 0,
          'min' => 0,
          'name' => 'assigned_persons',
          'post_type' => 
          array (
            0 => 'person',
          ),
          'return_format' => 'id',
          'storage_name' => 'assigned_persons',
          'type' => 'relationship',
        ),
        'capacity' => 
        array (
          'canonical_name' => 'capacity',
          'default_value' => 1,
          'instructions' => 'Aantal vrijwilligers benodigd. Snapshot van template op moment van expansie.',
          'key' => 'field_dienst_shift_capacity',
          'label' => 'Capaciteit',
          'min' => 0,
          'name' => 'capacity',
          'step' => 1,
          'storage_name' => 'capacity',
          'type' => 'number',
        ),
        'dienst_type_id' => 
        array (
          'allow_null' => 0,
          'canonical_name' => 'dienst_type_id',
          'instructions' => 'Welke inschrijftaak is dit?',
          'key' => 'field_dienst_shift_dienst_type',
          'label' => 'Inschrijftaak',
          'multiple' => 0,
          'name' => 'dienst_type_id',
          'post_type' => 
          array (
            0 => 'dienst_type',
          ),
          'required' => 1,
          'return_format' => 'id',
          'storage_name' => 'dienst_type_id',
          'type' => 'post_object',
          'ui' => 1,
        ),
        'end_datetime' => 
        array (
          'canonical_name' => 'end_datetime',
          'display_format' => 'd-m-Y H:i',
          'first_day' => 1,
          'instructions' => 'Wanneer eindigt de inschrijftaak?',
          'key' => 'field_dienst_shift_end_datetime',
          'label' => 'Einde',
          'name' => 'end_datetime',
          'required' => 1,
          'return_format' => 'Y-m-d H:i:s',
          'storage_format' => 'Y-m-d H:i:s',
          'storage_name' => 'end_datetime',
          'timezone' => 'site',
          'type' => 'date_time_picker',
          'wire_format' => 'Y-m-d\\TH:i:sP',
        ),
        'iva_waived' => 
        array (
          'canonical_name' => 'iva_waived',
          'default_value' => 0,
          'instructions' => 'Aanvinken als er bij deze specifieke inschrijftaak geen alcohol geschonken wordt (bv. zaterdag voor 15:00). Overschrijft de IVA-eis voor uitsluitend deze planning.',
          'key' => 'field_dienst_shift_iva_waived',
          'label' => 'IVA niet vereist voor deze inschrijftaak',
          'name' => 'iva_waived',
          'storage_name' => 'iva_waived',
          'type' => 'true_false',
          'ui' => 1,
        ),
        'notes' => 
        array (
          'canonical_name' => 'notes',
          'instructions' => 'Vrij toelichtingsveld.',
          'key' => 'field_dienst_shift_notes',
          'label' => 'Notities',
          'name' => 'notes',
          'new_lines' => '',
          'rows' => 2,
          'storage_name' => 'notes',
          'type' => 'textarea',
        ),
        'start_datetime' => 
        array (
          'canonical_name' => 'start_datetime',
          'display_format' => 'd-m-Y H:i',
          'first_day' => 1,
          'instructions' => 'Wanneer begint de inschrijftaak?',
          'key' => 'field_dienst_shift_start_datetime',
          'label' => 'Start',
          'name' => 'start_datetime',
          'required' => 1,
          'return_format' => 'Y-m-d H:i:s',
          'storage_format' => 'Y-m-d H:i:s',
          'storage_name' => 'start_datetime',
          'timezone' => 'site',
          'type' => 'date_time_picker',
          'wire_format' => 'Y-m-d\\TH:i:sP',
        ),
        'status' => 
        array (
          'allow_null' => 0,
          'canonical_name' => 'status',
          'choices' => 
          array (
            'geannuleerd' => 'Geannuleerd',
            'open' => 'Open',
            'vol' => 'Vol',
            'voltooid' => 'Voltooid',
          ),
          'default_value' => 'open',
          'instructions' => 'Huidige status van de inschrijftaak.',
          'key' => 'field_dienst_shift_status',
          'label' => 'Status',
          'multiple' => 0,
          'name' => 'status',
          'required' => 1,
          'return_format' => 'value',
          'storage_name' => 'status',
          'type' => 'select',
          'ui' => 0,
        ),
        'template_id' => 
        array (
          'allow_null' => 1,
          'canonical_name' => 'template_id',
          'instructions' => 'Welk sjabloon heeft deze inschrijftaak uitgerold? Leeg = los ingepland.',
          'key' => 'field_dienst_shift_template',
          'label' => 'Sjabloon (optioneel)',
          'multiple' => 0,
          'name' => 'template_id',
          'post_type' => 
          array (
            0 => 'shift_template',
          ),
          'return_format' => 'id',
          'storage_name' => 'template_id',
          'type' => 'post_object',
          'ui' => 1,
        ),
      ),
      'kind' => 'post',
    ),
    'dienst_type' => 
    array (
      'fields' => 
      array (
        'cancellation_early_email_body' => 
        array (
          'canonical_name' => 'cancellation_early_email_body',
          'default_value' => 'Hoi {naam},

Je inschrijftaak {dienst} op {datum} van {tijd} tot {eindtijd} gaat helaas niet door.

Deze annulering is minimaal 48 uur voor aanvang doorgegeven. De inschrijftaak telt daarom niet mee voor je vrijwilligersplicht. Kies een nieuwe inschrijftaak via Rondo.',
          'instructions' => 'Beschikbare variabelen: {naam}, {dienst}, {datum}, {tijd}, {eindtijd}, {medevrijwilligers}.',
          'key' => 'field_dienst_type_cancellation_early_email_body',
          'label' => 'Tekst annulering minimaal 48 uur vooraf',
          'name' => 'cancellation_early_email_body',
          'new_lines' => '',
          'rows' => 8,
          'storage_name' => 'cancellation_early_email_body',
          'type' => 'textarea',
        ),
        'cancellation_early_email_subject' => 
        array (
          'canonical_name' => 'cancellation_early_email_subject',
          'default_value' => 'Je inschrijftaak {dienst} op {datum} gaat niet door',
          'instructions' => 'Onderwerp wanneer een inschrijftaak minimaal 48 uur voor aanvang wordt geannuleerd en niet meetelt.',
          'key' => 'field_dienst_type_cancellation_early_email_subject',
          'label' => 'Onderwerp annulering minimaal 48 uur vooraf',
          'name' => 'cancellation_early_email_subject',
          'storage_name' => 'cancellation_early_email_subject',
          'type' => 'text',
        ),
        'cancellation_last_minute_email_body' => 
        array (
          'canonical_name' => 'cancellation_last_minute_email_body',
          'default_value' => 'Hoi {naam},

Je inschrijftaak {dienst} op {datum} van {tijd} tot {eindtijd} gaat helaas niet door.

Deze annulering is minder dan 48 uur voor aanvang doorgegeven. De inschrijftaak telt daarom wel mee voor je vrijwilligersplicht. Je hoeft hiervoor geen nieuwe inschrijftaak te kiezen.',
          'instructions' => 'Beschikbare variabelen: {naam}, {dienst}, {datum}, {tijd}, {eindtijd}, {medevrijwilligers}.',
          'key' => 'field_dienst_type_cancellation_last_minute_email_body',
          'label' => 'Tekst annulering binnen 48 uur',
          'name' => 'cancellation_last_minute_email_body',
          'new_lines' => '',
          'rows' => 8,
          'storage_name' => 'cancellation_last_minute_email_body',
          'type' => 'textarea',
        ),
        'cancellation_last_minute_email_subject' => 
        array (
          'canonical_name' => 'cancellation_last_minute_email_subject',
          'default_value' => 'Je inschrijftaak {dienst} op {datum} gaat niet door',
          'instructions' => 'Onderwerp wanneer een inschrijftaak binnen 48 uur voor aanvang wordt geannuleerd en wel meetelt.',
          'key' => 'field_dienst_type_cancellation_last_minute_email_subject',
          'label' => 'Onderwerp annulering binnen 48 uur',
          'name' => 'cancellation_last_minute_email_subject',
          'storage_name' => 'cancellation_last_minute_email_subject',
          'type' => 'text',
        ),
        'color' => 
        array (
          'canonical_name' => 'color',
          'default_value' => '#6b7280',
          'instructions' => 'Kleur voor de kalender-UI.',
          'key' => 'field_dienst_type_color',
          'label' => 'Kleur',
          'name' => 'color',
          'storage_name' => 'color',
          'type' => 'color_picker',
        ),
        'default_capacity' => 
        array (
          'canonical_name' => 'default_capacity',
          'default_value' => 1,
          'instructions' => 'Standaard aantal personen per inschrijftaak. 0 = onbeperkt.',
          'key' => 'field_dienst_type_default_capacity',
          'label' => 'Standaard capaciteit',
          'min' => 0,
          'name' => 'default_capacity',
          'step' => 1,
          'storage_name' => 'default_capacity',
          'type' => 'number',
        ),
        'description' => 
        array (
          'canonical_name' => 'description',
          'instructions' => 'Korte uitleg van wat deze inschrijftaak inhoudt. Zichtbaar voor vrijwilligers bij het aanmelden.',
          'key' => 'field_dienst_type_description',
          'label' => 'Omschrijving',
          'name' => 'description',
          'new_lines' => '',
          'rows' => 3,
          'storage_name' => 'description',
          'type' => 'textarea',
        ),
        'iva_required' => 
        array (
          'canonical_name' => 'iva_required',
          'default_value' => 0,
          'instructions' => 'Aanvinken als deze inschrijftaak een IVA-certificaat vereist (alcohol).',
          'key' => 'field_dienst_type_iva_required',
          'label' => 'IVA vereist',
          'name' => 'iva_required',
          'storage_name' => 'iva_required',
          'type' => 'true_false',
          'ui' => 1,
          'ui_off_text' => 'Nee',
          'ui_on_text' => 'Ja',
        ),
        'reminder_email_body' => 
        array (
          'canonical_name' => 'reminder_email_body',
          'default_value' => 'Hoi {naam},

Dit is een herinnering voor je inschrijftaak {dienst} op {datum} van {tijd} tot {eindtijd}.

Je doet deze inschrijftaak samen met {medevrijwilligers}.',
          'instructions' => 'Beschikbare variabelen: {naam}, {dienst}, {datum}, {tijd}, {eindtijd}, {medevrijwilligers}.',
          'key' => 'field_dienst_type_reminder_email_body',
          'label' => 'Tekst herinneringsmail',
          'name' => 'reminder_email_body',
          'new_lines' => '',
          'rows' => 8,
          'storage_name' => 'reminder_email_body',
          'type' => 'textarea',
        ),
        'reminder_email_subject' => 
        array (
          'canonical_name' => 'reminder_email_subject',
          'default_value' => 'Herinnering: {dienst} op {datum}',
          'instructions' => 'Onderwerp voor de herinneringen 2 weken, 1 week en 2 dagen voor de inschrijftaak.',
          'key' => 'field_dienst_type_reminder_email_subject',
          'label' => 'Onderwerp herinneringsmail',
          'name' => 'reminder_email_subject',
          'storage_name' => 'reminder_email_subject',
          'type' => 'text',
        ),
        'required_pool' => 
        array (
          'allow_null' => 1,
          'canonical_name' => 'required_pool',
          'instructions' => 'Optioneel: alleen leden van deze poule kunnen aanmelden (bv. Schoonmaakpoule).',
          'key' => 'field_dienst_type_required_pool',
          'label' => 'Vereiste poule',
          'multiple' => 0,
          'name' => 'required_pool',
          'post_type' => 
          array (
            0 => 'commissie',
          ),
          'return_format' => 'id',
          'storage_name' => 'required_pool',
          'type' => 'post_object',
          'ui' => 1,
        ),
        'sleutel_involved' => 
        array (
          'canonical_name' => 'sleutel_involved',
          'default_value' => 0,
          'instructions' => 'Aanvinken als deze inschrijftaak met sleutels werkt.',
          'key' => 'field_dienst_type_sleutel_involved',
          'label' => 'Sleutel betrokken',
          'name' => 'sleutel_involved',
          'storage_name' => 'sleutel_involved',
          'type' => 'true_false',
          'ui' => 1,
          'ui_off_text' => 'Nee',
          'ui_on_text' => 'Ja',
        ),
        'survey_email_body' => 
        array (
          'canonical_name' => 'survey_email_body',
          'default_value' => 'Hoi {naam},

Bedankt voor je inzet bij {dienst}. We horen graag hoe de inschrijftaak is verlopen. Wil je onze korte enquête invullen?',
          'instructions' => 'Beschikbare variabelen: {naam}, {dienst}, {datum}, {tijd}, {eindtijd}, {medevrijwilligers}.',
          'key' => 'field_dienst_type_survey_email_body',
          'label' => 'Tekst enquêtemail',
          'name' => 'survey_email_body',
          'new_lines' => '',
          'rows' => 8,
          'storage_name' => 'survey_email_body',
          'type' => 'textarea',
        ),
        'survey_email_subject' => 
        array (
          'canonical_name' => 'survey_email_subject',
          'default_value' => 'Hoe ging je inschrijftaak {dienst}?',
          'instructions' => 'Onderwerp van de e-mail die één dag na de inschrijftaak wordt verstuurd.',
          'key' => 'field_dienst_type_survey_email_subject',
          'label' => 'Onderwerp enquêtemail',
          'name' => 'survey_email_subject',
          'storage_name' => 'survey_email_subject',
          'type' => 'text',
        ),
        'survey_url' => 
        array (
          'canonical_name' => 'survey_url',
          'default_value' => '',
          'instructions' => 'Link naar de enquête voor deze inschrijftaak. Zonder link wordt geen enquêtemail verstuurd.',
          'key' => 'field_dienst_type_survey_url',
          'label' => 'Google Forms-link',
          'name' => 'survey_url',
          'placeholder' => 'https://docs.google.com/forms/...',
          'storage_name' => 'survey_url',
          'type' => 'url',
        ),
        'vog_required' => 
        array (
          'canonical_name' => 'vog_required',
          'default_value' => 0,
          'instructions' => 'Aanvinken als deze inschrijftaak een geldige VOG vereist.',
          'key' => 'field_dienst_type_vog_required',
          'label' => 'VOG vereist',
          'name' => 'vog_required',
          'storage_name' => 'vog_required',
          'type' => 'true_false',
          'ui' => 1,
          'ui_off_text' => 'Nee',
          'ui_on_text' => 'Ja',
        ),
      ),
      'kind' => 'post',
    ),
    'discipline_case' => 
    array (
      'fields' => 
      array (
        'administrative_fee' => 
        array (
          'canonical_name' => 'administrative_fee',
          'instructions' => 'Administratiekosten in euro\'s',
          'key' => 'field_discipline_administrative_fee',
          'label' => 'Administratiekosten',
          'min' => 0,
          'name' => 'administrative_fee',
          'prepend' => '€',
          'required' => 0,
          'step' => 0.01,
          'storage_name' => 'administrative_fee',
          'type' => 'number',
        ),
        'away_team' => 
        array (
          'allow_null' => 1,
          'canonical_name' => 'away_team',
          'instructions' => 'Uitteam van de wedstrijd (automatisch gekoppeld via Sportlink)',
          'key' => 'field_discipline_away_team',
          'label' => 'Uitteam',
          'multiple' => 0,
          'name' => 'away_team',
          'post_type' => 
          array (
            0 => 'team',
          ),
          'required' => 0,
          'return_format' => 'id',
          'storage_name' => 'away_team',
          'type' => 'post_object',
        ),
        'charge_codes' => 
        array (
          'canonical_name' => 'charge_codes',
          'instructions' => 'Artikelnummer van de overtreding',
          'key' => 'field_discipline_charge_codes',
          'label' => 'Artikelnummer',
          'name' => 'charge_codes',
          'required' => 0,
          'storage_name' => 'charge_codes',
          'type' => 'text',
        ),
        'charge_description' => 
        array (
          'canonical_name' => 'charge_description',
          'instructions' => 'Omschrijving van de overtreding',
          'key' => 'field_discipline_charge_description',
          'label' => 'Artikelomschrijving',
          'name' => 'charge_description',
          'required' => 0,
          'rows' => 3,
          'storage_name' => 'charge_description',
          'type' => 'textarea',
        ),
        'dossier_id' => 
        array (
          'canonical_name' => 'dossier_id',
          'instructions' => 'Uniek identificatienummer uit Sportlink',
          'key' => 'field_discipline_dossier_id',
          'label' => 'Dossier ID',
          'name' => 'dossier_id',
          'required' => 1,
          'storage_name' => 'dossier_id',
          'type' => 'text',
        ),
        'home_team' => 
        array (
          'allow_null' => 1,
          'canonical_name' => 'home_team',
          'instructions' => 'Thuisteam van de wedstrijd (automatisch gekoppeld via Sportlink)',
          'key' => 'field_discipline_home_team',
          'label' => 'Thuisteam',
          'multiple' => 0,
          'name' => 'home_team',
          'post_type' => 
          array (
            0 => 'team',
          ),
          'required' => 0,
          'return_format' => 'id',
          'storage_name' => 'home_team',
          'type' => 'post_object',
        ),
        'is_charged' => 
        array (
          'allow_null' => 0,
          'canonical_name' => 'is_charged',
          'choices' => 
          array (
            '' => 'Nee',
            'rondo' => 'Ja, Rondo',
            'sportlink' => 'Ja, Sportlink',
          ),
          'default_value' => '',
          'instructions' => 'Bron van doorbelasting (Sportlink of Rondo)',
          'key' => 'field_discipline_is_charged',
          'label' => 'Doorbelast',
          'name' => 'is_charged',
          'return_format' => 'value',
          'storage_name' => 'is_charged',
          'type' => 'select',
        ),
        'match_date' => 
        array (
          'canonical_name' => 'match_date',
          'display_format' => 'd-m-Y',
          'instructions' => 'Datum van de wedstrijd/het incident',
          'key' => 'field_discipline_match_date',
          'label' => 'Wedstrijddatum',
          'name' => 'match_date',
          'required' => 0,
          'return_format' => 'Ymd',
          'storage_format' => 'Ymd',
          'storage_name' => 'match_date',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'match_description' => 
        array (
          'canonical_name' => 'match_description',
          'instructions' => 'Omschrijving van de wedstrijd (bijv. Team A - Team B)',
          'key' => 'field_discipline_match_description',
          'label' => 'Wedstrijdomschrijving',
          'name' => 'match_description',
          'required' => 0,
          'storage_name' => 'match_description',
          'type' => 'text',
        ),
        'person' => 
        array (
          'allow_null' => 1,
          'canonical_name' => 'person',
          'instructions' => 'Gekoppelde persoon (optioneel - kan later worden gekoppeld)',
          'key' => 'field_discipline_person',
          'label' => 'Persoon',
          'multiple' => 0,
          'name' => 'person',
          'post_type' => 
          array (
            0 => 'person',
          ),
          'required' => 0,
          'return_format' => 'id',
          'storage_name' => 'person',
          'type' => 'post_object',
        ),
        'processing_date' => 
        array (
          'canonical_name' => 'processing_date',
          'display_format' => 'd-m-Y',
          'instructions' => 'Datum waarop de zaak is verwerkt',
          'key' => 'field_discipline_processing_date',
          'label' => 'Verwerkingsdatum',
          'name' => 'processing_date',
          'required' => 0,
          'return_format' => 'Ymd',
          'storage_format' => 'Ymd',
          'storage_name' => 'processing_date',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'sanction_description' => 
        array (
          'canonical_name' => 'sanction_description',
          'instructions' => 'Omschrijving van de opgelegde straf',
          'key' => 'field_discipline_sanction_description',
          'label' => 'Strafbeschrijving',
          'name' => 'sanction_description',
          'required' => 0,
          'rows' => 3,
          'storage_name' => 'sanction_description',
          'type' => 'textarea',
        ),
        'team_name' => 
        array (
          'canonical_name' => 'team_name',
          'instructions' => 'Naam van het betrokken team',
          'key' => 'field_discipline_team_name',
          'label' => 'Teamnaam',
          'name' => 'team_name',
          'required' => 0,
          'storage_name' => 'team_name',
          'type' => 'text',
        ),
      ),
      'kind' => 'post',
    ),
    'person' => 
    array (
      'fields' => 
      array (
        '_nikki_2022_saldo' => 
        array (
          'canonical_name' => '_nikki_2022_saldo',
          'instructions' => 'Openstaand saldo 2022',
          'key' => 'field_nikki_2022_saldo',
          'label' => 'NIKKI 2022 saldo',
          'name' => '_nikki_2022_saldo',
          'storage_name' => '_nikki_2022_saldo',
          'type' => 'number',
        ),
        '_nikki_2022_status' => 
        array (
          'canonical_name' => '_nikki_2022_status',
          'instructions' => 'Betalingsstatus 2022',
          'key' => 'field_nikki_2022_status',
          'label' => 'NIKKI 2022 status',
          'name' => '_nikki_2022_status',
          'storage_name' => '_nikki_2022_status',
          'type' => 'text',
        ),
        '_nikki_2022_total' => 
        array (
          'canonical_name' => '_nikki_2022_total',
          'instructions' => 'Hoofdsom contributie 2022',
          'key' => 'field_nikki_2022_total',
          'label' => 'NIKKI 2022 totaal',
          'name' => '_nikki_2022_total',
          'storage_name' => '_nikki_2022_total',
          'type' => 'number',
        ),
        '_nikki_2023_saldo' => 
        array (
          'canonical_name' => '_nikki_2023_saldo',
          'instructions' => 'Openstaand saldo 2023',
          'key' => 'field_nikki_2023_saldo',
          'label' => 'NIKKI 2023 saldo',
          'name' => '_nikki_2023_saldo',
          'storage_name' => '_nikki_2023_saldo',
          'type' => 'number',
        ),
        '_nikki_2023_status' => 
        array (
          'canonical_name' => '_nikki_2023_status',
          'instructions' => 'Betalingsstatus 2023',
          'key' => 'field_nikki_2023_status',
          'label' => 'NIKKI 2023 status',
          'name' => '_nikki_2023_status',
          'storage_name' => '_nikki_2023_status',
          'type' => 'text',
        ),
        '_nikki_2023_total' => 
        array (
          'canonical_name' => '_nikki_2023_total',
          'instructions' => 'Hoofdsom contributie 2023',
          'key' => 'field_nikki_2023_total',
          'label' => 'NIKKI 2023 totaal',
          'name' => '_nikki_2023_total',
          'storage_name' => '_nikki_2023_total',
          'type' => 'number',
        ),
        '_nikki_2024_saldo' => 
        array (
          'canonical_name' => '_nikki_2024_saldo',
          'instructions' => 'Openstaand saldo 2024',
          'key' => 'field_nikki_2024_saldo',
          'label' => 'NIKKI 2024 saldo',
          'name' => '_nikki_2024_saldo',
          'storage_name' => '_nikki_2024_saldo',
          'type' => 'number',
        ),
        '_nikki_2024_status' => 
        array (
          'canonical_name' => '_nikki_2024_status',
          'instructions' => 'Betalingsstatus 2024',
          'key' => 'field_nikki_2024_status',
          'label' => 'NIKKI 2024 status',
          'name' => '_nikki_2024_status',
          'storage_name' => '_nikki_2024_status',
          'type' => 'text',
        ),
        '_nikki_2024_total' => 
        array (
          'canonical_name' => '_nikki_2024_total',
          'instructions' => 'Hoofdsom contributie 2024',
          'key' => 'field_nikki_2024_total',
          'label' => 'NIKKI 2024 totaal',
          'name' => '_nikki_2024_total',
          'storage_name' => '_nikki_2024_total',
          'type' => 'number',
        ),
        '_nikki_2025_saldo' => 
        array (
          'canonical_name' => '_nikki_2025_saldo',
          'instructions' => 'Openstaand saldo 2025',
          'key' => 'field_nikki_2025_saldo',
          'label' => 'NIKKI 2025 saldo',
          'name' => '_nikki_2025_saldo',
          'storage_name' => '_nikki_2025_saldo',
          'type' => 'number',
        ),
        '_nikki_2025_status' => 
        array (
          'canonical_name' => '_nikki_2025_status',
          'instructions' => 'Betalingsstatus 2025',
          'key' => 'field_nikki_2025_status',
          'label' => 'NIKKI 2025 status',
          'name' => '_nikki_2025_status',
          'storage_name' => '_nikki_2025_status',
          'type' => 'text',
        ),
        '_nikki_2025_total' => 
        array (
          'canonical_name' => '_nikki_2025_total',
          'instructions' => 'Hoofdsom contributie 2025',
          'key' => 'field_nikki_2025_total',
          'label' => 'NIKKI 2025 totaal',
          'name' => '_nikki_2025_total',
          'storage_name' => '_nikki_2025_total',
          'type' => 'number',
        ),
        'addresses' => 
        array (
          'button_label' => 'Add Address',
          'canonical_name' => 'addresses',
          'key' => 'field_addresses',
          'label' => 'Addresses',
          'layout' => 'block',
          'name' => 'addresses',
          'storage_name' => 'addresses',
          'sub_fields' => 
          array (
            'address_label' => 
            array (
              'canonical_name' => 'address_label',
              'key' => 'field_address_label',
              'label' => 'Label',
              'name' => 'address_label',
              'placeholder' => 'e.g., Home, Work',
              'storage_name' => 'address_label',
              'type' => 'text',
            ),
            'city' => 
            array (
              'canonical_name' => 'city',
              'key' => 'field_address_city',
              'label' => 'City',
              'name' => 'city',
              'storage_name' => 'city',
              'type' => 'text',
            ),
            'country' => 
            array (
              'canonical_name' => 'country',
              'key' => 'field_address_country',
              'label' => 'Country',
              'name' => 'country',
              'storage_name' => 'country',
              'type' => 'text',
            ),
            'country_code' => 
            array (
              'canonical_name' => 'country_code',
              'key' => 'field_address_country_code',
              'label' => 'Country Code',
              'maxlength' => 3,
              'name' => 'country_code',
              'placeholder' => 'e.g. NL',
              'storage_name' => 'country_code',
              'type' => 'text',
            ),
            'house_number' => 
            array (
              'canonical_name' => 'house_number',
              'key' => 'field_address_house_number',
              'label' => 'Huisnummer',
              'name' => 'house_number',
              'storage_name' => 'house_number',
              'type' => 'text',
            ),
            'house_number_addition' => 
            array (
              'canonical_name' => 'house_number_addition',
              'key' => 'field_address_house_number_addition',
              'label' => 'Toevoeging',
              'name' => 'house_number_addition',
              'storage_name' => 'house_number_addition',
              'type' => 'text',
            ),
            'postal_code' => 
            array (
              'canonical_name' => 'postal_code',
              'key' => 'field_address_postal_code',
              'label' => 'Postal code',
              'name' => 'postal_code',
              'storage_name' => 'postal_code',
              'type' => 'text',
            ),
            'state' => 
            array (
              'canonical_name' => 'state',
              'key' => 'field_address_state',
              'label' => 'State/Province',
              'name' => 'state',
              'storage_name' => 'state',
              'type' => 'text',
            ),
            'street_name' => 
            array (
              'canonical_name' => 'street_name',
              'key' => 'field_address_street_name',
              'label' => 'Straatnaam',
              'name' => 'street_name',
              'storage_name' => 'street_name',
              'type' => 'text',
            ),
          ),
          'type' => 'repeater',
        ),
        'betaalde_vrijwilliger' => 
        array (
          'canonical_name' => 'betaalde_vrijwilliger',
          'default_value' => 0,
          'instructions' => 'Aanvinken als deze persoon een vergoeding ontvangt voor vrijwilligerswerk. Betaalde vrijwilligers zijn automatisch vrijgesteld van de vrijwilligersplicht van twee inschrijftaken.',
          'key' => 'field_betaalde_vrijwilliger',
          'label' => 'Betaalde vrijwilliger',
          'name' => 'betaalde_vrijwilliger',
          'storage_name' => 'betaalde_vrijwilliger',
          'type' => 'true_false',
          'ui' => 1,
          'ui_off_text' => 'Nee',
          'ui_on_text' => 'Ja',
        ),
        'birthdate' => 
        array (
          'canonical_name' => 'birthdate',
          'display_format' => 'd F Y',
          'key' => 'field_birthdate',
          'label' => 'Birthdate',
          'name' => 'birthdate',
          'readonly' => 1,
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'birthdate',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'company_name' => 
        array (
          'canonical_name' => 'company_name',
          'instructions' => 'Optioneel voor externe contacten. Bij een contact zonder persoonsnaam wordt dit de weergavenaam.',
          'key' => 'field_company_name',
          'label' => 'Bedrijfsnaam',
          'name' => 'company_name',
          'required' => 0,
          'storage_name' => 'company_name',
          'type' => 'text',
        ),
        'datum_foto' => 
        array (
          'canonical_name' => 'datum_foto',
          'display_format' => 'Y-m-d',
          'key' => 'field_datum_foto',
          'label' => 'Datum foto',
          'name' => 'datum-foto',
          'readonly' => 1,
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'datum-foto',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'datum_iva' => 
        array (
          'canonical_name' => 'datum_iva',
          'display_format' => 'd F Y',
          'instructions' => 'Datum waarop het IVA-certificaat (Instructie Verantwoord Alcoholschenken) is behaald. Vereist voor wie achter de bar staat.',
          'key' => 'field_datum_iva',
          'label' => 'Datum IVA-certificaat',
          'name' => 'datum-iva',
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'datum-iva',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'datum_overlijden' => 
        array (
          'canonical_name' => 'datum_overlijden',
          'display_format' => 'd F Y',
          'key' => 'field_datum_overlijden',
          'label' => 'Datum overlijden',
          'name' => 'datum-overlijden',
          'readonly' => 1,
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'datum-overlijden',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'datum_vog' => 
        array (
          'canonical_name' => 'datum_vog',
          'display_format' => 'Y-m-d',
          'key' => 'field_datum_vog',
          'label' => 'Datum VOG',
          'name' => 'datum-vog',
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'datum-vog',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'email_1' => 
        array (
          'canonical_name' => 'email_1',
          'key' => 'field_email_1',
          'label' => 'Email',
          'name' => 'email_1',
          'placeholder' => 'naam@voorbeeld.nl',
          'storage_name' => 'email_1',
          'type' => 'text',
        ),
        'email_2' => 
        array (
          'canonical_name' => 'email_2',
          'key' => 'field_email_2',
          'label' => 'Email (2e)',
          'name' => 'email_2',
          'placeholder' => 'naam@voorbeeld.nl',
          'storage_name' => 'email_2',
          'type' => 'text',
        ),
        'financiele_blokkade' => 
        array (
          'canonical_name' => 'financiele_blokkade',
          'default_value' => 0,
          'key' => 'field_financiele_blokkade',
          'label' => 'Financiële blokkade',
          'name' => 'financiele-blokkade',
          'storage_name' => 'financiele-blokkade',
          'type' => 'true_false',
          'ui' => 1,
          'ui_off_text' => 'Nee',
          'ui_on_text' => 'Ja',
        ),
        'first_name' => 
        array (
          'canonical_name' => 'first_name',
          'key' => 'field_first_name',
          'label' => 'First Name',
          'name' => 'first_name',
          'required' => 0,
          'storage_name' => 'first_name',
          'type' => 'text',
        ),
        'former_member' => 
        array (
          'canonical_name' => 'former_member',
          'default_value' => 0,
          'key' => 'field_former_member',
          'label' => 'Oud-lid',
          'message' => 'Dit lid is niet meer actief bij de club',
          'name' => 'former_member',
          'readonly' => 1,
          'storage_name' => 'former_member',
          'type' => 'true_false',
          'ui' => 1,
        ),
        'freescout_id' => 
        array (
          'canonical_name' => 'freescout_id',
          'key' => 'field_freescout_id',
          'label' => 'Freescout ID',
          'name' => 'freescout-id',
          'storage_name' => 'freescout-id',
          'type' => 'number',
        ),
        'gender' => 
        array (
          'ajax' => 0,
          'allow_null' => 1,
          'canonical_name' => 'gender',
          'choices' => 
          array (
            'female' => 'Female',
            'male' => 'Male',
            'non_binary' => 'Non-binary',
            'other' => 'Other',
            'prefer_not_to_say' => 'Prefer not to say',
          ),
          'default_value' => '',
          'key' => 'field_gender',
          'label' => 'Gender',
          'multiple' => 0,
          'name' => 'gender',
          'placeholder' => '',
          'return_format' => 'value',
          'storage_name' => 'gender',
          'type' => 'select',
          'ui' => 0,
        ),
        'huidig_vrijwilliger' => 
        array (
          'canonical_name' => 'huidig_vrijwilliger',
          'default_value' => 0,
          'key' => 'field_huidig_vrijwilliger',
          'label' => 'Huidig vrijwilliger',
          'name' => 'huidig-vrijwilliger',
          'storage_name' => 'huidig-vrijwilliger',
          'type' => 'true_false',
          'ui' => 1,
          'ui_off_text' => 'Nee',
          'ui_on_text' => 'Ja',
        ),
        'infix' => 
        array (
          'canonical_name' => 'infix',
          'instructions' => 'Tussenvoegsel (e.g., van, de, van der)',
          'key' => 'field_infix',
          'label' => 'Infix',
          'name' => 'infix',
          'readonly' => 1,
          'storage_name' => 'infix',
          'type' => 'text',
        ),
        'is_sponsor' => 
        array (
          'canonical_name' => 'is_sponsor',
          'default_value' => 0,
          'instructions' => 'Actieve sponsorrol, onafhankelijk van het persoonstype.',
          'key' => 'field_is_sponsor',
          'label' => 'Sponsor',
          'name' => 'is_sponsor',
          'required' => 0,
          'storage_name' => 'is_sponsor',
          'type' => 'true_false',
          'ui' => 1,
          'ui_off_text' => 'Nee',
          'ui_on_text' => 'Ja',
        ),
        'isparent' => 
        array (
          'canonical_name' => 'isparent',
          'default_value' => 0,
          'key' => 'field_isparent',
          'label' => 'isParent',
          'name' => 'isparent',
          'storage_name' => 'isparent',
          'type' => 'true_false',
          'ui' => 1,
          'ui_off_text' => 'No',
          'ui_on_text' => 'Yes',
        ),
        'iva_approved' => 
        array (
          'canonical_name' => 'iva_approved',
          'default_value' => 0,
          'instructions' => 'Alleen aanvinken na controle van het certificaat door een admin/kantinebeheerder.',
          'key' => 'field_iva_approved',
          'label' => 'IVA goedgekeurd',
          'name' => 'iva-approved',
          'storage_name' => 'iva-approved',
          'type' => 'true_false',
          'ui' => 1,
          'ui_off_text' => 'Niet goedgekeurd',
          'ui_on_text' => 'Goedgekeurd',
        ),
        'iva_certificaat' => 
        array (
          'canonical_name' => 'iva_certificaat',
          'instructions' => 'Upload het IVA-certificaat als bewijsstuk.',
          'key' => 'field_iva_certificaat',
          'label' => 'IVA-certificaat (PDF)',
          'library' => 'all',
          'mime_types' => 'pdf,jpg,jpeg,png',
          'name' => 'iva-certificaat',
          'return_format' => 'array',
          'storage_name' => 'iva-certificaat',
          'type' => 'file',
        ),
        'knvb_id' => 
        array (
          'canonical_name' => 'knvb_id',
          'key' => 'field_knvb_id',
          'label' => 'KNVB ID',
          'name' => 'knvb-id',
          'readonly' => 1,
          'storage_name' => 'knvb-id',
          'type' => 'text',
        ),
        'last_name' => 
        array (
          'canonical_name' => 'last_name',
          'key' => 'field_last_name',
          'label' => 'Last Name',
          'name' => 'last_name',
          'required' => 0,
          'storage_name' => 'last_name',
          'type' => 'text',
        ),
        'leeftijdsgroep' => 
        array (
          'canonical_name' => 'leeftijdsgroep',
          'key' => 'field_leeftijdsgroep',
          'label' => 'Leeftijdsgroep',
          'name' => 'leeftijdsgroep',
          'readonly' => 1,
          'storage_name' => 'leeftijdsgroep',
          'type' => 'text',
        ),
        'lid_sinds' => 
        array (
          'canonical_name' => 'lid_sinds',
          'display_format' => 'd F Y',
          'key' => 'field_lid_sinds',
          'label' => 'Lid sinds',
          'name' => 'lid-sinds',
          'readonly' => 1,
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'lid-sinds',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'lid_tot' => 
        array (
          'canonical_name' => 'lid_tot',
          'display_format' => 'd F Y',
          'key' => 'field_lid_tot',
          'label' => 'Lid tot',
          'name' => 'lid-tot',
          'readonly' => 1,
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'lid-tot',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'mobile_1' => 
        array (
          'canonical_name' => 'mobile_1',
          'key' => 'field_mobile_1',
          'label' => 'Mobiel',
          'name' => 'mobile_1',
          'placeholder' => '+31612345678',
          'storage_name' => 'mobile_1',
          'type' => 'text',
        ),
        'mobile_2' => 
        array (
          'canonical_name' => 'mobile_2',
          'key' => 'field_mobile_2',
          'label' => 'Mobiel (2e)',
          'name' => 'mobile_2',
          'placeholder' => '+31612345678',
          'storage_name' => 'mobile_2',
          'type' => 'text',
        ),
        'nickname' => 
        array (
          'canonical_name' => 'nickname',
          'key' => 'field_nickname',
          'label' => 'Nickname',
          'name' => 'nickname',
          'storage_name' => 'nickname',
          'type' => 'text',
        ),
        'nikki_contributie_status' => 
        array (
          'canonical_name' => 'nikki_contributie_status',
          'key' => 'field_nikki_contributie_status',
          'label' => 'NIKKI contributie status',
          'media_upload' => 1,
          'name' => 'nikki-contributie-status',
          'storage_name' => 'nikki-contributie-status',
          'tabs' => 'all',
          'toolbar' => 'full',
          'type' => 'wysiwyg',
        ),
        'onboarding_email_lid_sent' => 
        array (
          'canonical_name' => 'onboarding_email_lid_sent',
          'display_format' => 'd-m-Y H:i',
          'instructions' => 'Tijdstip waarop de onboarding e-mail voor nieuwe leden is verstuurd',
          'key' => 'field_onboarding_email_lid_sent',
          'label' => 'Onboarding e-mail lid verstuurd',
          'name' => 'onboarding-email-lid-sent',
          'readonly' => 1,
          'required' => 0,
          'return_format' => 'Y-m-d H:i:s',
          'storage_format' => 'Y-m-d H:i:s',
          'storage_name' => 'onboarding-email-lid-sent',
          'timezone' => 'site',
          'type' => 'date_time_picker',
          'wire_format' => 'Y-m-d\\TH:i:sP',
        ),
        'onboarding_email_vrijwilliger_sent' => 
        array (
          'canonical_name' => 'onboarding_email_vrijwilliger_sent',
          'display_format' => 'd-m-Y H:i',
          'instructions' => 'Tijdstip waarop de onboarding e-mail voor nieuwe vrijwilligers is verstuurd',
          'key' => 'field_onboarding_email_vrijwilliger_sent',
          'label' => 'Onboarding e-mail vrijwilliger verstuurd',
          'name' => 'onboarding-email-vrijwilliger-sent',
          'readonly' => 1,
          'required' => 0,
          'return_format' => 'Y-m-d H:i:s',
          'storage_format' => 'Y-m-d H:i:s',
          'storage_name' => 'onboarding-email-vrijwilliger-sent',
          'timezone' => 'site',
          'type' => 'date_time_picker',
          'wire_format' => 'Y-m-d\\TH:i:sP',
        ),
        'person_type' => 
        array (
          'ajax' => 0,
          'allow_null' => 0,
          'canonical_name' => 'person_type',
          'choices' => 
          array (
            'contact' => 'Contact',
            'member' => 'Lid / ouder',
          ),
          'default_value' => 'member',
          'instructions' => 'Gebruik Contact voor externe relaties. Sponsorschap wordt los van het persoonstype vastgelegd.',
          'key' => 'field_person_type',
          'label' => 'Persoonstype',
          'multiple' => 0,
          'name' => 'person_type',
          'placeholder' => '',
          'required' => 0,
          'return_format' => 'value',
          'storage_name' => 'person_type',
          'type' => 'select',
          'ui' => 0,
        ),
        'photo_gallery' => 
        array (
          'canonical_name' => 'photo_gallery',
          'key' => 'field_photo_gallery',
          'label' => 'Photo Gallery',
          'library' => 'all',
          'max' => 50,
          'min' => 0,
          'name' => 'photo_gallery',
          'preview_size' => 'medium',
          'return_format' => 'array',
          'storage_name' => 'photo_gallery',
          'type' => 'gallery',
        ),
        'pronouns' => 
        array (
          'canonical_name' => 'pronouns',
          'key' => 'field_pronouns',
          'label' => 'Pronouns',
          'name' => 'pronouns',
          'placeholder' => 'e.g., they/them, she/her, he/him',
          'storage_name' => 'pronouns',
          'type' => 'text',
        ),
        'relationships' => 
        array (
          'button_label' => 'Add Relationship',
          'canonical_name' => 'relationships',
          'key' => 'field_relationships',
          'label' => 'Relationships',
          'layout' => 'table',
          'name' => 'relationships',
          'storage_name' => 'relationships',
          'sub_fields' => 
          array (
            'person_name' => 
            array (
              'canonical_name' => 'person_name',
              'read_only' => true,
              'storage_name' => NULL,
              'type' => 'string',
            ),
            'person_thumbnail' => 
            array (
              'canonical_name' => 'person_thumbnail',
              'read_only' => true,
              'storage_name' => NULL,
              'type' => 'string',
            ),
            'related_person_id' => 
            array (
              'canonical_name' => 'related_person_id',
              'key' => 'field_related_person',
              'label' => 'Person',
              'name' => 'related_person',
              'post_type' => 
              array (
                0 => 'person',
              ),
              'return_format' => 'id',
              'storage_name' => 'related_person',
              'type' => 'post_object',
            ),
            'relationship_label' => 
            array (
              'canonical_name' => 'relationship_label',
              'key' => 'field_relationship_label',
              'label' => 'Custom Label',
              'name' => 'relationship_label',
              'placeholder' => 'e.g., Brother-in-law',
              'storage_name' => 'relationship_label',
              'type' => 'text',
            ),
            'relationship_name' => 
            array (
              'canonical_name' => 'relationship_name',
              'read_only' => true,
              'storage_name' => NULL,
              'type' => 'string',
            ),
            'relationship_slug' => 
            array (
              'canonical_name' => 'relationship_slug',
              'read_only' => true,
              'storage_name' => NULL,
              'type' => 'string',
            ),
            'relationship_type_id' => 
            array (
              'canonical_name' => 'relationship_type_id',
              'field_type' => 'select',
              'key' => 'field_relationship_type',
              'label' => 'Type',
              'name' => 'relationship_type',
              'return_format' => 'id',
              'storage_name' => 'relationship_type',
              'taxonomy' => 'relationship_type',
              'type' => 'taxonomy',
            ),
          ),
          'type' => 'repeater',
        ),
        'spelactiviteit' => 
        array (
          'canonical_name' => 'spelactiviteit',
          'key' => 'field_spelactiviteit',
          'label' => 'Spelactiviteit',
          'name' => 'spelactiviteit',
          'readonly' => 1,
          'storage_name' => 'spelactiviteit',
          'type' => 'text',
        ),
        'sponsit_contact_id' => 
        array (
          'canonical_name' => 'sponsit_contact_id',
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_is_sponsor',
                'operator' => '==',
                'value' => '1',
              ),
            ),
          ),
          'instructions' => 'Stabiele bron-ID voor de Sponsit-sync.',
          'key' => 'field_sponsit_contact_id',
          'label' => 'Sponsit contact-ID',
          'name' => 'sponsit_contact_id',
          'required' => 0,
          'storage_name' => 'sponsit_contact_id',
          'type' => 'text',
        ),
        'sponsit_person_id' => 
        array (
          'canonical_name' => 'sponsit_person_id',
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_is_sponsor',
                'operator' => '==',
                'value' => '1',
              ),
            ),
          ),
          'instructions' => 'Stabiele contactpersoon-ID voor de Sponsit-sync.',
          'key' => 'field_sponsit_person_id',
          'label' => 'Sponsit persoon-ID',
          'name' => 'sponsit_person_id',
          'required' => 0,
          'storage_name' => 'sponsit_person_id',
          'type' => 'text',
        ),
        'sponsor_pass_variant' => 
        array (
          'ajax' => 0,
          'allow_null' => 0,
          'canonical_name' => 'sponsor_pass_variant',
          'choices' => 
          array (
            'awc_sponsor' => 'AWC Sponsor',
            'businessclub' => 'Businessclub AWC',
          ),
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_is_sponsor',
                'operator' => '==',
                'value' => '1',
              ),
            ),
          ),
          'default_value' => '',
          'instructions' => 'Kies welke witte toegangspas deze sponsor krijgt.',
          'key' => 'field_sponsor_pass_variant',
          'label' => 'Pasvariant',
          'multiple' => 0,
          'name' => 'sponsor_pass_variant',
          'placeholder' => '',
          'required' => 0,
          'return_format' => 'value',
          'storage_name' => 'sponsor_pass_variant',
          'type' => 'select',
          'ui' => 0,
        ),
        'telephone_1' => 
        array (
          'canonical_name' => 'telephone_1',
          'key' => 'field_telephone_1',
          'label' => 'Telefoon',
          'name' => 'telephone_1',
          'placeholder' => '+31201234567',
          'storage_name' => 'telephone_1',
          'type' => 'text',
        ),
        'telephone_2' => 
        array (
          'canonical_name' => 'telephone_2',
          'key' => 'field_telephone_2',
          'label' => 'Telefoon (2e)',
          'name' => 'telephone_2',
          'placeholder' => '+31201234567',
          'storage_name' => 'telephone_2',
          'type' => 'text',
        ),
        'type_lid' => 
        array (
          'canonical_name' => 'type_lid',
          'key' => 'field_type_lid',
          'label' => 'Type lid',
          'name' => 'type-lid',
          'readonly' => 1,
          'storage_name' => 'type-lid',
          'type' => 'text',
        ),
        'vergoeding_reden' => 
        array (
          'allow_null' => 1,
          'canonical_name' => 'vergoeding_reden',
          'choices' => 
          array (
            'anders' => 'Anders',
            'kantinebeheerder' => 'Kantinebeheerder',
            'knvb-trainer' => 'KNVB-norm trainer (loonadministratie)',
            'weekend-inwerk' => 'Weekend-inwerk (tijdelijk)',
          ),
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_betaalde_vrijwilliger',
                'operator' => '==',
                'value' => '1',
              ),
            ),
          ),
          'default_value' => '',
          'instructions' => 'Reden waarom deze persoon een vergoeding ontvangt.',
          'key' => 'field_vergoeding_reden',
          'label' => 'Reden vergoeding',
          'multiple' => 0,
          'name' => 'vergoeding_reden',
          'return_format' => 'value',
          'storage_name' => 'vergoeding_reden',
          'type' => 'select',
          'ui' => 0,
        ),
        'vergoeding_tot' => 
        array (
          'canonical_name' => 'vergoeding_tot',
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_betaalde_vrijwilliger',
                'operator' => '==',
                'value' => '1',
              ),
            ),
          ),
          'display_format' => 'd F Y',
          'instructions' => 'Optioneel. Leeg laten voor doorlopende vergoeding. Vooral relevant voor weekend-inwerk.',
          'key' => 'field_vergoeding_tot',
          'label' => 'Vergoeding loopt tot',
          'name' => 'vergoeding_tot',
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'vergoeding_tot',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'vog_email_sent_date' => 
        array (
          'backend' => 'meta',
          'canonical_name' => 'vog_email_sent_date',
          'label' => 'vog_email_sent_date',
          'storage_format' => 'Y-m-d H:i:s',
          'storage_name' => 'vog_email_sent_date',
          'timezone' => 'site',
          'type' => 'date_time_picker',
          'wire_format' => 'Y-m-d\\TH:i:sP',
        ),
        'vog_justis_submitted_date' => 
        array (
          'backend' => 'meta',
          'canonical_name' => 'vog_justis_submitted_date',
          'label' => 'vog_justis_submitted_date',
          'storage_format' => 'Y-m-d H:i:s',
          'storage_name' => 'vog_justis_submitted_date',
          'timezone' => 'site',
          'type' => 'date_time_picker',
          'wire_format' => 'Y-m-d\\TH:i:sP',
        ),
        'vog_reminder_sent_date' => 
        array (
          'backend' => 'meta',
          'canonical_name' => 'vog_reminder_sent_date',
          'label' => 'vog_reminder_sent_date',
          'storage_format' => 'Y-m-d H:i:s',
          'storage_name' => 'vog_reminder_sent_date',
          'timezone' => 'site',
          'type' => 'date_time_picker',
          'wire_format' => 'Y-m-d\\TH:i:sP',
        ),
        'vrijgesteld_handmatig' => 
        array (
          'canonical_name' => 'vrijgesteld_handmatig',
          'default_value' => 0,
          'instructions' => 'Aanvinken voor personen die niet automatisch onder een vrijstelling vallen maar wel vrijgesteld moeten worden van de vrijwilligersplicht van twee inschrijftaken (bv. ereleden, langdurig zieken).',
          'key' => 'field_vrijgesteld_handmatig',
          'label' => 'Handmatig vrijgesteld',
          'name' => 'vrijgesteld_handmatig',
          'storage_name' => 'vrijgesteld_handmatig',
          'type' => 'true_false',
          'ui' => 1,
          'ui_off_text' => 'Nee',
          'ui_on_text' => 'Ja',
        ),
        'vrijstelling_reden' => 
        array (
          'canonical_name' => 'vrijstelling_reden',
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_vrijgesteld_handmatig',
                'operator' => '==',
                'value' => '1',
              ),
            ),
          ),
          'instructions' => 'Korte toelichting voor de vrijstelling. Zichtbaar voor admins.',
          'key' => 'field_vrijstelling_reden',
          'label' => 'Reden vrijstelling',
          'name' => 'vrijstelling_reden',
          'new_lines' => '',
          'rows' => 2,
          'storage_name' => 'vrijstelling_reden',
          'type' => 'textarea',
        ),
        'vrijstelling_seizoen' => 
        array (
          'canonical_name' => 'vrijstelling_seizoen',
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_vrijgesteld_handmatig',
                'operator' => '==',
                'value' => '1',
              ),
            ),
          ),
          'instructions' => 'Specifiek seizoen waarvoor de vrijstelling geldt (bv. 2026-2027). Leeg laten voor doorlopende vrijstelling.',
          'key' => 'field_vrijstelling_seizoen',
          'label' => 'Geldig voor seizoen',
          'maxlength' => 9,
          'name' => 'vrijstelling_seizoen',
          'placeholder' => '2026-2027',
          'storage_name' => 'vrijstelling_seizoen',
          'type' => 'text',
        ),
        'vrijwilliger_sinds' => 
        array (
          'canonical_name' => 'vrijwilliger_sinds',
          'display_format' => 'd F Y',
          'key' => 'field_vrijwilliger_sinds',
          'label' => 'Vrijwilliger sinds',
          'name' => 'vrijwilliger-sinds',
          'readonly' => 1,
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'vrijwilliger-sinds',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'wacht_op_overschrijving' => 
        array (
          'canonical_name' => 'wacht_op_overschrijving',
          'default_value' => 0,
          'key' => 'field_wacht_op_overschrijving',
          'label' => 'Wacht op overschrijving',
          'message' => 'Dit lid komt van een andere club en moet nog worden overgeschreven',
          'name' => 'wacht_op_overschrijving',
          'readonly' => 1,
          'storage_name' => 'wacht_op_overschrijving',
          'type' => 'true_false',
          'ui' => 1,
        ),
        'work_history' => 
        array (
          'button_label' => 'Add Position',
          'canonical_name' => 'work_history',
          'key' => 'field_work_history',
          'label' => 'Team History',
          'layout' => 'block',
          'name' => 'work_history',
          'storage_name' => 'work_history',
          'sub_fields' => 
          array (
            'description' => 
            array (
              'canonical_name' => 'description',
              'key' => 'field_work_description',
              'label' => 'Description',
              'name' => 'description',
              'rows' => 3,
              'storage_name' => 'description',
              'type' => 'textarea',
            ),
            'end_date' => 
            array (
              'canonical_name' => 'end_date',
              'display_format' => 'F Y',
              'key' => 'field_work_end_date',
              'label' => 'End Date',
              'name' => 'end_date',
              'return_format' => 'Y-m-d',
              'storage_format' => 'Ymd',
              'storage_name' => 'end_date',
              'timezone' => 'date_only',
              'type' => 'date_picker',
              'wire_format' => 'Y-m-d',
            ),
            'entity_type' => 
            array (
              'canonical_name' => 'entity_type',
              'key' => 'field_work_entity_type',
              'label' => 'Entity Type',
              'name' => 'entity_type',
              'readonly' => 1,
              'storage_name' => 'entity_type',
              'type' => 'text',
            ),
            'is_current' => 
            array (
              'canonical_name' => 'is_current',
              'key' => 'field_work_is_current',
              'label' => 'Current Position',
              'message' => 'Currently works here',
              'name' => 'is_current',
              'storage_name' => 'is_current',
              'type' => 'true_false',
              'ui' => 1,
            ),
            'job_title' => 
            array (
              'canonical_name' => 'job_title',
              'key' => 'field_work_job_title',
              'label' => 'Job Title',
              'name' => 'job_title',
              'storage_name' => 'job_title',
              'type' => 'text',
            ),
            'start_date' => 
            array (
              'canonical_name' => 'start_date',
              'display_format' => 'F Y',
              'key' => 'field_work_start_date',
              'label' => 'Start Date',
              'name' => 'start_date',
              'return_format' => 'Y-m-d',
              'storage_format' => 'Ymd',
              'storage_name' => 'start_date',
              'timezone' => 'date_only',
              'type' => 'date_picker',
              'wire_format' => 'Y-m-d',
            ),
            'team_id' => 
            array (
              'allow_null' => 1,
              'canonical_name' => 'team_id',
              'key' => 'field_work_company',
              'label' => 'Team / Commissie',
              'name' => 'team',
              'post_type' => 
              array (
                0 => 'team',
                1 => 'commissie',
              ),
              'return_format' => 'id',
              'storage_name' => 'team',
              'type' => 'post_object',
            ),
            'team_name_text' => 
            array (
              'canonical_name' => 'team_name_text',
              'conditional_logic' => 
              array (
                0 => 
                array (
                  0 => 
                  array (
                    'field' => 'field_work_company',
                    'operator' => '==empty',
                  ),
                ),
              ),
              'instructions' => 'Free-text team name for historical Sportlink teams that don\'t exist in Rondo Club as posts (e.g. "Zaterdag E5 (seizoen 2014/\'15)"). Only used when Team / Commissie is empty.',
              'key' => 'field_work_team_name_text',
              'label' => 'Team name (historical)',
              'name' => 'team_name_text',
              'storage_name' => 'team_name_text',
              'type' => 'text',
            ),
          ),
          'type' => 'repeater',
        ),
      ),
      'kind' => 'post',
    ),
    'relationship_type' => 
    array (
      'fields' => 
      array (
        'inverse_relationship_type' => 
        array (
          'allow_null' => 1,
          'canonical_name' => 'inverse_relationship_type',
          'field_type' => 'select',
          'instructions' => 'Select the inverse relationship type. For example, if this is \'Parent\', select \'Child\'. If this is \'Sibling\', select \'Sibling\' (same type). Leave empty if this relationship type has no inverse.',
          'key' => 'field_inverse_relationship_type',
          'label' => 'Inverse Relationship Type',
          'multiple' => 0,
          'name' => 'inverse_relationship_type',
          'required' => 0,
          'return_format' => 'id',
          'storage_name' => 'inverse_relationship_type',
          'taxonomy' => 'relationship_type',
          'type' => 'taxonomy',
        ),
      ),
      'kind' => 'term',
    ),
    'rondo_feedback' => 
    array (
      'fields' => 
      array (
        'actual_behavior' => 
        array (
          'canonical_name' => 'actual_behavior',
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_feedback_type',
                'operator' => '==',
                'value' => 'bug',
              ),
            ),
          ),
          'instructions' => 'What actually happened?',
          'key' => 'field_feedback_actual_behavior',
          'label' => 'Actual Behavior',
          'name' => 'actual_behavior',
          'required' => 0,
          'rows' => 4,
          'storage_name' => 'actual_behavior',
          'type' => 'textarea',
        ),
        'app_version' => 
        array (
          'canonical_name' => 'app_version',
          'instructions' => 'Stadion version at time of submission',
          'key' => 'field_feedback_app_version',
          'label' => 'App Version',
          'name' => 'app_version',
          'required' => 0,
          'storage_name' => 'app_version',
          'type' => 'text',
        ),
        'browser_info' => 
        array (
          'canonical_name' => 'browser_info',
          'instructions' => 'Browser and OS information (auto-captured)',
          'key' => 'field_feedback_browser_info',
          'label' => 'Browser Info',
          'name' => 'browser_info',
          'required' => 0,
          'storage_name' => 'browser_info',
          'type' => 'text',
        ),
        'expected_behavior' => 
        array (
          'canonical_name' => 'expected_behavior',
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_feedback_type',
                'operator' => '==',
                'value' => 'bug',
              ),
            ),
          ),
          'instructions' => 'What did you expect to happen?',
          'key' => 'field_feedback_expected_behavior',
          'label' => 'Expected Behavior',
          'name' => 'expected_behavior',
          'required' => 0,
          'rows' => 4,
          'storage_name' => 'expected_behavior',
          'type' => 'textarea',
        ),
        'feedback_type' => 
        array (
          'ajax' => 0,
          'allow_null' => 0,
          'canonical_name' => 'feedback_type',
          'choices' => 
          array (
            'bug' => 'Bug Report',
            'feature_request' => 'Feature Request',
          ),
          'default_value' => '',
          'instructions' => '',
          'key' => 'field_feedback_type',
          'label' => 'Type',
          'multiple' => 0,
          'name' => 'feedback_type',
          'required' => 1,
          'return_format' => 'value',
          'storage_name' => 'feedback_type',
          'type' => 'select',
          'ui' => 0,
        ),
        'priority' => 
        array (
          'ajax' => 0,
          'allow_null' => 0,
          'canonical_name' => 'priority',
          'choices' => 
          array (
            'critical' => 'Critical',
            'high' => 'High',
            'low' => 'Low',
            'medium' => 'Medium',
          ),
          'default_value' => 'medium',
          'instructions' => '',
          'key' => 'field_feedback_priority',
          'label' => 'Priority',
          'multiple' => 0,
          'name' => 'priority',
          'required' => 0,
          'return_format' => 'value',
          'storage_name' => 'priority',
          'type' => 'select',
          'ui' => 0,
        ),
        'status' => 
        array (
          'ajax' => 0,
          'allow_null' => 0,
          'canonical_name' => 'status',
          'choices' => 
          array (
            'declined' => 'Declined',
            'in_progress' => 'In Progress',
            'new' => 'New',
            'resolved' => 'Resolved',
          ),
          'default_value' => 'new',
          'instructions' => '',
          'key' => 'field_feedback_status',
          'label' => 'Status',
          'multiple' => 0,
          'name' => 'status',
          'required' => 1,
          'return_format' => 'value',
          'storage_name' => 'status',
          'type' => 'select',
          'ui' => 0,
        ),
        'steps_to_reproduce' => 
        array (
          'canonical_name' => 'steps_to_reproduce',
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_feedback_type',
                'operator' => '==',
                'value' => 'bug',
              ),
            ),
          ),
          'instructions' => 'Describe the steps to reproduce this issue',
          'key' => 'field_feedback_steps_to_reproduce',
          'label' => 'Steps to Reproduce',
          'name' => 'steps_to_reproduce',
          'required' => 0,
          'rows' => 4,
          'storage_name' => 'steps_to_reproduce',
          'type' => 'textarea',
        ),
        'url_context' => 
        array (
          'canonical_name' => 'url_context',
          'instructions' => 'Page URL where feedback was submitted',
          'key' => 'field_feedback_url_context',
          'label' => 'URL Context',
          'name' => 'url_context',
          'required' => 0,
          'storage_name' => 'url_context',
          'type' => 'text',
        ),
        'use_case' => 
        array (
          'canonical_name' => 'use_case',
          'conditional_logic' => 
          array (
            0 => 
            array (
              0 => 
              array (
                'field' => 'field_feedback_type',
                'operator' => '==',
                'value' => 'feature_request',
              ),
            ),
          ),
          'instructions' => 'Describe how you would use this feature',
          'key' => 'field_feedback_use_case',
          'label' => 'Use Case',
          'name' => 'use_case',
          'required' => 0,
          'rows' => 4,
          'storage_name' => 'use_case',
          'type' => 'textarea',
        ),
      ),
      'kind' => 'post',
    ),
    'rondo_invoice' => 
    array (
      'fields' => 
      array (
        'due_date' => 
        array (
          'canonical_name' => 'due_date',
          'display_format' => 'd-m-Y',
          'instructions' => 'Datum waarop de betaling vervalt',
          'key' => 'field_invoice_due_date',
          'label' => 'Vervaldatum',
          'name' => 'due_date',
          'required' => 0,
          'return_format' => 'Ymd',
          'storage_format' => 'Ymd',
          'storage_name' => 'due_date',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'invoice_number' => 
        array (
          'canonical_name' => 'invoice_number',
          'instructions' => 'Wordt automatisch gegenereerd',
          'key' => 'field_invoice_number',
          'label' => 'Factuurnummer',
          'name' => 'invoice_number',
          'readonly' => 1,
          'required' => 1,
          'storage_name' => 'invoice_number',
          'type' => 'text',
        ),
        'invoice_type' => 
        array (
          'allow_null' => 1,
          'canonical_name' => 'invoice_type',
          'choices' => 
          array (
            'discipline' => 'Tuchtzaak',
            'membership' => 'Lidmaatschapsbijdrage',
            'volunteer_fine' => 'Vrijwilligersboete',
          ),
          'default_value' => 'discipline',
          'instructions' => 'Type factuur: tuchtzaak of lidmaatschapsbijdrage',
          'key' => 'field_invoice_type',
          'label' => 'Factuurtype',
          'name' => 'invoice_type',
          'required' => 0,
          'storage_name' => 'invoice_type',
          'type' => 'select',
        ),
        'line_items' => 
        array (
          'button_label' => 'Voeg regel toe',
          'canonical_name' => 'line_items',
          'instructions' => 'Factuurregels met gekoppelde tuchtzaken',
          'key' => 'field_invoice_line_items',
          'label' => 'Regelitems',
          'layout' => 'table',
          'name' => 'line_items',
          'required' => 0,
          'storage_name' => 'line_items',
          'sub_fields' => 
          array (
            'amount' => 
            array (
              'canonical_name' => 'amount',
              'instructions' => '',
              'key' => 'field_invoice_line_amount',
              'label' => 'Bedrag',
              'min' => 0,
              'name' => 'amount',
              'prepend' => '€',
              'required' => 1,
              'step' => 0.01,
              'storage_name' => 'amount',
              'type' => 'number',
            ),
            'description' => 
            array (
              'canonical_name' => 'description',
              'instructions' => '',
              'key' => 'field_invoice_line_description',
              'label' => 'Omschrijving',
              'name' => 'description',
              'required' => 0,
              'storage_name' => 'description',
              'type' => 'text',
            ),
            'discipline_case' => 
            array (
              'allow_null' => 0,
              'canonical_name' => 'discipline_case',
              'instructions' => '',
              'key' => 'field_invoice_line_discipline_case',
              'label' => 'Tuchtzaak',
              'multiple' => 0,
              'name' => 'discipline_case',
              'post_type' => 
              array (
                0 => 'discipline_case',
              ),
              'required' => 1,
              'return_format' => 'id',
              'storage_name' => 'discipline_case',
              'type' => 'post_object',
            ),
          ),
          'type' => 'repeater',
        ),
        'payment_link' => 
        array (
          'canonical_name' => 'payment_link',
          'instructions' => 'URL naar Rabobank betaalverzoek',
          'key' => 'field_invoice_payment_link',
          'label' => 'Betaallink',
          'name' => 'payment_link',
          'required' => 0,
          'storage_name' => 'payment_link',
          'type' => 'url',
        ),
        'pdf_path' => 
        array (
          'canonical_name' => 'pdf_path',
          'instructions' => 'Relatief pad naar gegenereerde PDF (intern gebruik)',
          'key' => 'field_invoice_pdf_path',
          'label' => 'PDF pad',
          'name' => 'pdf_path',
          'required' => 0,
          'storage_name' => 'pdf_path',
          'type' => 'text',
        ),
        'person' => 
        array (
          'allow_null' => 0,
          'canonical_name' => 'person',
          'instructions' => 'Persoon aan wie de factuur is gericht',
          'key' => 'field_invoice_person',
          'label' => 'Persoon',
          'multiple' => 0,
          'name' => 'person',
          'post_type' => 
          array (
            0 => 'person',
          ),
          'required' => 1,
          'return_format' => 'id',
          'storage_name' => 'person',
          'type' => 'post_object',
        ),
        'qr_code_path' => 
        array (
          'canonical_name' => 'qr_code_path',
          'instructions' => 'Relatief pad naar QR code PNG (intern gebruik)',
          'key' => 'field_invoice_qr_code_path',
          'label' => 'QR code pad',
          'name' => 'qr_code_path',
          'required' => 0,
          'storage_name' => 'qr_code_path',
          'type' => 'text',
        ),
        'sent_date' => 
        array (
          'canonical_name' => 'sent_date',
          'display_format' => 'd-m-Y',
          'instructions' => 'Datum waarop de factuur is verstuurd',
          'key' => 'field_invoice_sent_date',
          'label' => 'Verzenddatum',
          'name' => 'sent_date',
          'required' => 0,
          'return_format' => 'Ymd',
          'storage_format' => 'Ymd',
          'storage_name' => 'sent_date',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'status' => 
        array (
          'allow_null' => 0,
          'canonical_name' => 'status',
          'choices' => 
          array (
            'draft' => 'Concept',
            'overdue' => 'Verlopen',
            'paid' => 'Betaald',
            'sent' => 'Verstuurd',
          ),
          'default_value' => 'draft',
          'instructions' => 'Huidige status van de factuur',
          'key' => 'field_invoice_status',
          'label' => 'Status',
          'name' => 'status',
          'required' => 1,
          'storage_name' => 'status',
          'type' => 'select',
        ),
        'total_amount' => 
        array (
          'canonical_name' => 'total_amount',
          'instructions' => 'Totaal van alle regelitems',
          'key' => 'field_invoice_total_amount',
          'label' => 'Totaalbedrag',
          'min' => 0,
          'name' => 'total_amount',
          'prepend' => '€',
          'required' => 1,
          'step' => 0.01,
          'storage_name' => 'total_amount',
          'type' => 'number',
        ),
      ),
      'kind' => 'post',
    ),
    'rondo_todo' => 
    array (
      'fields' => 
      array (
        'awaiting_since' => 
        array (
          'canonical_name' => 'awaiting_since',
          'display_format' => 'F j, Y g:i a',
          'instructions' => 'Timestamp when todo entered awaiting status',
          'key' => 'field_todo_awaiting_since',
          'label' => 'Awaiting Since',
          'name' => 'awaiting_since',
          'required' => 0,
          'return_format' => 'Y-m-d H:i:s',
          'storage_format' => 'Y-m-d H:i:s',
          'storage_name' => 'awaiting_since',
          'timezone' => 'site',
          'type' => 'date_time_picker',
          'wire_format' => 'Y-m-d\\TH:i:sP',
        ),
        'due_date' => 
        array (
          'canonical_name' => 'due_date',
          'display_format' => 'F j, Y',
          'instructions' => 'Optional due date for this todo',
          'key' => 'field_todo_due_date',
          'label' => 'Due Date',
          'name' => 'due_date',
          'required' => 0,
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'due_date',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'notes' => 
        array (
          'canonical_name' => 'notes',
          'instructions' => 'Additional notes or details for this todo',
          'key' => 'field_todo_notes',
          'label' => 'Notes',
          'media_upload' => 0,
          'name' => 'notes',
          'required' => 0,
          'storage_name' => 'notes',
          'tabs' => 'visual',
          'toolbar' => 'basic',
          'type' => 'wysiwyg',
        ),
        'related_persons' => 
        array (
          'allow_null' => 0,
          'canonical_name' => 'related_persons',
          'instructions' => 'Select the people this todo is related to',
          'key' => 'field_todo_related_person',
          'label' => 'Related People',
          'multiple' => 1,
          'name' => 'related_persons',
          'post_type' => 
          array (
            0 => 'person',
          ),
          'required' => 1,
          'return_format' => 'id',
          'storage_name' => 'related_persons',
          'type' => 'post_object',
        ),
      ),
      'kind' => 'post',
    ),
    'shift_template' => 
    array (
      'fields' => 
      array (
        'active_from' => 
        array (
          'canonical_name' => 'active_from',
          'display_format' => 'd F Y',
          'instructions' => 'Eerste datum waarop dit sjabloon inschrijftaken genereert.',
          'key' => 'field_shift_template_active_from',
          'label' => 'Actief vanaf',
          'name' => 'active_from',
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'active_from',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'active_until' => 
        array (
          'canonical_name' => 'active_until',
          'display_format' => 'd F Y',
          'instructions' => 'Laatste datum (inclusief). Leeg = doorlopend.',
          'key' => 'field_shift_template_active_until',
          'label' => 'Actief tot',
          'name' => 'active_until',
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'active_until',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'capacity' => 
        array (
          'canonical_name' => 'capacity',
          'instructions' => 'Overschrijft de standaard van de inschrijftaak. Leeg = gebruik de standaard.',
          'key' => 'field_shift_template_capacity',
          'label' => 'Capaciteit',
          'min' => 0,
          'name' => 'capacity',
          'step' => 1,
          'storage_name' => 'capacity',
          'type' => 'number',
        ),
        'day_of_week' => 
        array (
          'allow_null' => 0,
          'canonical_name' => 'day_of_week',
          'choices' => 
          array (
            1 => 'Maandag',
            2 => 'Dinsdag',
            3 => 'Woensdag',
            4 => 'Donderdag',
            5 => 'Vrijdag',
            6 => 'Zaterdag',
            7 => 'Zondag',
          ),
          'default_value' => '',
          'instructions' => 'Op welke dag valt de inschrijftaak?',
          'key' => 'field_shift_template_day_of_week',
          'label' => 'Dag van de week',
          'multiple' => 0,
          'name' => 'day_of_week',
          'required' => 1,
          'return_format' => 'value',
          'storage_name' => 'day_of_week',
          'type' => 'select',
          'ui' => 0,
        ),
        'dienst_type_id' => 
        array (
          'allow_null' => 0,
          'canonical_name' => 'dienst_type_id',
          'instructions' => 'Welke inschrijftaak rolt dit sjabloon uit?',
          'key' => 'field_shift_template_dienst_type',
          'label' => 'Inschrijftaak',
          'multiple' => 0,
          'name' => 'dienst_type_id',
          'post_type' => 
          array (
            0 => 'dienst_type',
          ),
          'required' => 1,
          'return_format' => 'id',
          'storage_name' => 'dienst_type_id',
          'type' => 'post_object',
          'ui' => 1,
        ),
        'end_time' => 
        array (
          'canonical_name' => 'end_time',
          'display_format' => 'H:i',
          'instructions' => 'Eindtijd van de inschrijftaak (HH:MM).',
          'key' => 'field_shift_template_end_time',
          'label' => 'Eindtijd',
          'name' => 'end_time',
          'required' => 1,
          'return_format' => 'H:i',
          'storage_format' => 'H:i:s',
          'storage_name' => 'end_time',
          'timezone' => 'site',
          'type' => 'time_picker',
          'wire_format' => 'H:i',
        ),
        'iva_waived' => 
        array (
          'canonical_name' => 'iva_waived',
          'default_value' => 0,
          'instructions' => 'Aanvinken als bij de uitgerolde inschrijftaken geen alcohol geschonken wordt (bv. zaterdagochtend voor 15:00). De expander zet dezelfde waarde op elke uitgerolde inschrijftaak. Heeft alleen effect als de inschrijftaak IVA vereist.',
          'key' => 'field_shift_template_iva_waived',
          'label' => 'IVA niet vereist voor deze sjabloon',
          'name' => 'iva_waived',
          'storage_name' => 'iva_waived',
          'type' => 'true_false',
          'ui' => 1,
        ),
        'notes' => 
        array (
          'canonical_name' => 'notes',
          'instructions' => 'Vrij toelichtingsveld voor admins (bv. "alleen bij thuiswedstrijden").',
          'key' => 'field_shift_template_notes',
          'label' => 'Notities',
          'name' => 'notes',
          'new_lines' => '',
          'rows' => 2,
          'storage_name' => 'notes',
          'type' => 'textarea',
        ),
        'start_time' => 
        array (
          'canonical_name' => 'start_time',
          'display_format' => 'H:i',
          'instructions' => 'Begintijd van de inschrijftaak (HH:MM).',
          'key' => 'field_shift_template_start_time',
          'label' => 'Starttijd',
          'name' => 'start_time',
          'required' => 1,
          'return_format' => 'H:i',
          'storage_format' => 'H:i:s',
          'storage_name' => 'start_time',
          'timezone' => 'site',
          'type' => 'time_picker',
          'wire_format' => 'H:i',
        ),
      ),
      'kind' => 'post',
    ),
    'taakuitleg' => 
    array (
      'fields' => 
      array (
        'dienst_types' => 
        array (
          'canonical_name' => 'dienst_types',
          'filters' => 
          array (
            0 => 'search',
          ),
          'instructions' => 'Bij welke inschrijftaken hoort deze uitleg? Eén uitleg mag aan meerdere inschrijftaken hangen.',
          'key' => 'field_taakuitleg_dienst_types',
          'label' => 'Inschrijftaken',
          'max' => 0,
          'min' => 0,
          'name' => 'dienst_types',
          'post_type' => 
          array (
            0 => 'dienst_type',
          ),
          'required' => 0,
          'return_format' => 'id',
          'storage_name' => 'dienst_types',
          'type' => 'relationship',
        ),
      ),
      'kind' => 'post',
    ),
    'team' => 
    array (
      'fields' => 
      array (
        'activiteit' => 
        array (
          'canonical_name' => 'activiteit',
          'key' => 'field_team_activiteit',
          'label' => 'Activiteit',
          'name' => 'activiteit',
          'readonly' => 1,
          'storage_name' => 'activiteit',
          'type' => 'text',
        ),
        'contact_info' => 
        array (
          'button_label' => 'Add Contact Method',
          'canonical_name' => 'contact_info',
          'key' => 'field_team_contact_info',
          'label' => 'Contact Information',
          'layout' => 'table',
          'name' => 'contact_info',
          'storage_name' => 'contact_info',
          'sub_fields' => 
          array (
            'contact_label' => 
            array (
              'canonical_name' => 'contact_label',
              'key' => 'field_team_contact_label',
              'label' => 'Label',
              'name' => 'contact_label',
              'placeholder' => 'e.g., HQ, Support',
              'storage_name' => 'contact_label',
              'type' => 'text',
            ),
            'contact_type' => 
            array (
              'canonical_name' => 'contact_type',
              'choices' => 
              array (
                'address' => 'Address',
                'email' => 'Email',
                'other' => 'Other',
                'phone' => 'Phone',
              ),
              'key' => 'field_team_contact_type',
              'label' => 'Type',
              'name' => 'contact_type',
              'storage_name' => 'contact_type',
              'type' => 'select',
            ),
            'contact_value' => 
            array (
              'canonical_name' => 'contact_value',
              'key' => 'field_team_contact_value',
              'label' => 'Value',
              'name' => 'contact_value',
              'storage_name' => 'contact_value',
              'type' => 'text',
            ),
          ),
          'type' => 'repeater',
        ),
        'gender' => 
        array (
          'canonical_name' => 'gender',
          'key' => 'field_team_gender',
          'label' => 'Gender',
          'name' => 'gender',
          'readonly' => 1,
          'storage_name' => 'gender',
          'type' => 'text',
        ),
        'kickoff_done_at' => 
        array (
          'canonical_name' => 'kickoff_done_at',
          'display_format' => 'd F Y',
          'instructions' => 'Datum waarop Guido (of bestuur) het vrijwilligersbeleid heeft besproken met dit team.',
          'key' => 'field_team_kickoff_done_at',
          'label' => 'Kickoff afgerond op',
          'name' => 'kickoff_done_at',
          'return_format' => 'Y-m-d',
          'storage_format' => 'Ymd',
          'storage_name' => 'kickoff_done_at',
          'timezone' => 'date_only',
          'type' => 'date_picker',
          'wire_format' => 'Y-m-d',
        ),
        'kickoff_notes' => 
        array (
          'canonical_name' => 'kickoff_notes',
          'instructions' => 'Opmerkingen uit het gesprek (acties, openstaande vragen).',
          'key' => 'field_team_kickoff_notes',
          'label' => 'Notities kickoff',
          'name' => 'kickoff_notes',
          'new_lines' => '',
          'rows' => 3,
          'storage_name' => 'kickoff_notes',
          'type' => 'textarea',
        ),
        'publicteamid' => 
        array (
          'canonical_name' => 'publicteamid',
          'key' => 'field_team_publicteamid',
          'label' => 'PublicTeamId',
          'name' => 'publicteamid',
          'readonly' => 1,
          'storage_name' => 'publicteamid',
          'type' => 'text',
        ),
        'website' => 
        array (
          'canonical_name' => 'website',
          'key' => 'field_team_website',
          'label' => 'Website',
          'name' => 'website',
          'storage_name' => 'website',
          'type' => 'url',
        ),
      ),
      'kind' => 'post',
    ),
  ),
  'schema_version' => 1,
  'reserved_exceptions' => 
  array (
    'dienst_shift' => 
    array (
      0 => 'status',
    ),
    'rondo_feedback' => 
    array (
      0 => 'status',
    ),
    'rondo_invoice' => 
    array (
      0 => 'status',
    ),
  ),
  'reserved_top_level' => 
  array (
    0 => 'id',
    1 => 'title',
    2 => 'slug',
    3 => 'status',
    4 => 'type',
    5 => 'author',
    6 => 'date',
    7 => 'date_gmt',
    8 => 'modified',
    9 => 'modified_gmt',
    10 => 'link',
    11 => 'featured_media',
    12 => 'template',
    13 => 'meta',
    14 => 'acf',
    15 => 'fields',
    16 => '_links',
    17 => '_embedded',
  ),
  'version' => 1,
);
