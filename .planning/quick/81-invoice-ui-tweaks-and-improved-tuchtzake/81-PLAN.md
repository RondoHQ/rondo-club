---
phase: quick-81
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/pages/Finance/FactuurDetail.jsx
  - includes/class-invoice-email-sender.php
autonomous: true
must_haves:
  truths:
    - "Reset button in test mode shows 'Reset factuur (test)' instead of 'Reset betaalstatus (test)'"
    - "Invoice detail header shows only the invoice number, not the member name"
    - "Member name still appears in the Lid card on the right side"
    - "Invoice email {tuchtzaken_lijst} renders an HTML table with columns Datum, Wedstrijd, Kaart, Bedrag"
    - "Kaart column shows 'Geel' for charge codes ending in -1, 'Rood' otherwise, with ' en schorsing' appended when sanction_description is 'uitsluiting'"
  artifacts:
    - path: "src/pages/Finance/FactuurDetail.jsx"
      provides: "Updated invoice detail UI"
    - path: "includes/class-invoice-email-sender.php"
      provides: "Updated email tuchtzaken table"
  key_links: []
---

<objective>
Three small fixes to the invoice system: rename the reset button, remove duplicate member name from header, and improve the tuchtzaken list in the invoice email from a vague `<ul>` to a clear HTML `<table>`.

Purpose: Better UX and clearer invoice emails for club administrators.
Output: Updated FactuurDetail.jsx and InvoiceEmailSender.php.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/pages/Finance/FactuurDetail.jsx
@includes/class-invoice-email-sender.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Invoice detail UI tweaks (rename reset button + remove duplicate name)</name>
  <files>src/pages/Finance/FactuurDetail.jsx</files>
  <action>
Two changes in FactuurDetail.jsx:

1. **Rename reset button** (around line 600): Change the button text from `Reset betaalstatus (test)` to `Reset factuur (test)`. Also update the confirm dialog text on line 167 — change "betaalstatus wilt resetten? Dit wist de betaallink, factuur-PDF, en betaalstatus" to "factuur wilt resetten? Dit wist de betaallink, factuur-PDF, en betaalstatus".

2. **Remove duplicate member name from header** (lines 245-253): Remove the `{invoice.person?.id && ( <Link ...> ... </Link> )}` block that shows the person name below the invoice number in the header card. The member name is already displayed in the "Lid" card on the right side (lines 330-363), so showing it in the header is redundant. Keep the h1 with `invoice.invoice_number` and the StatusBadge — just remove the person name link between them.
  </action>
  <verify>Run `npm run build` from the rondo-club directory to confirm the frontend compiles without errors. Visually inspect the JSX to confirm: (a) reset button says "Reset factuur (test)", (b) header card only shows invoice number and status badge, no person name link.</verify>
  <done>Reset button label says "Reset factuur (test)", confirm dialog updated, and invoice detail header shows only the invoice number (no duplicate member name).</done>
</task>

<task type="auto">
  <name>Task 2: Replace tuchtzaken email list with HTML table</name>
  <files>includes/class-invoice-email-sender.php</files>
  <action>
Replace the `$tuchtzaken_lijst` building block (lines 99-122) in the `send()` method. The current code builds a `<ul>` with `match_description` and `sanction_description`. Replace it with an HTML `<table>` with inline CSS for email client compatibility.

**New table columns:**
- **Datum**: Fetch `match_date` ACF field from the discipline case (`get_field('match_date', $case_id)`). This is stored as `Ymd` format (e.g., `20240215`). Convert to `d-m-Y` display format using `date('d-m-Y', strtotime($match_date))`. If empty, show `-`.
- **Wedstrijd**: Fetch `match_description` ACF field (already used). If empty, show `-`.
- **Kaart**: Derive from `charge_codes` ACF field (`get_field('charge_codes', $case_id)`). If the value ends with `-1`, display `Geel`. Otherwise display `Rood`. Then check `sanction_description` field (`get_field('sanction_description', $case_id)`) — if it equals `uitsluiting` (case-insensitive), append ` en schorsing` to the card text. If charge_codes is empty, show `-`.
- **Bedrag**: From the line item's `amount` field, formatted as `&euro; X,XX` using `number_format()` (same pattern as existing code).

For line items without a `discipline_case` (the `elseif` fallback branch), keep them as a simple row with the description spanning the first 3 columns and the amount in the last column.

**HTML table styling** (inline CSS for email compatibility):
```html
<table style="width:100%;border-collapse:collapse;font-size:14px;">
  <thead>
    <tr style="background-color:#f3f4f6;">
      <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;">Datum</th>
      <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;">Wedstrijd</th>
      <th style="padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;">Kaart</th>
      <th style="padding:8px 12px;text-align:right;border-bottom:2px solid #d1d5db;">Bedrag</th>
    </tr>
  </thead>
  <tbody>
    <!-- rows with alternating: no background / #f9fafb -->
    <tr>
      <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">15-02-2024</td>
      <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">Team A - Team B</td>
      <td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;">Geel</td>
      <td style="padding:8px 12px;text-align:right;border-bottom:1px solid #e5e7eb;">&euro; 25,00</td>
    </tr>
  </tbody>
</table>
```

Keep the total amount row outside the table (it is handled by the `{totaal_bedrag}` template variable separately).
  </action>
  <verify>Review the PHP code to confirm: (a) table HTML is well-formed with proper inline styles, (b) `match_date` is fetched and formatted from `Ymd` to `d-m-Y`, (c) `charge_codes` field determines Geel vs Rood logic, (d) `sanction_description === 'uitsluiting'` appends " en schorsing", (e) fallback rows for non-discipline items still work. Run a quick PHP syntax check: `php -l includes/class-invoice-email-sender.php`.</verify>
  <done>Invoice email `{tuchtzaken_lijst}` variable produces an HTML table with Datum, Wedstrijd, Kaart, Bedrag columns. Card type correctly derived from charge_codes field. Schorsing appended when sanction_description is uitsluiting.</done>
</task>

</tasks>

<verification>
1. `npm run build` passes (frontend compiles)
2. `php -l includes/class-invoice-email-sender.php` passes (no syntax errors)
3. Deploy to production and verify invoice detail page loads correctly
</verification>

<success_criteria>
- Reset button shows "Reset factuur (test)" in test mode
- Invoice detail header shows only invoice number, no member name
- Invoice email tuchtzaken section renders as a clean HTML table with Datum, Wedstrijd, Kaart, Bedrag columns
- Kaart column correctly shows Geel/Rood based on charge_codes, with " en schorsing" for uitsluiting cases
</success_criteria>

<output>
After completion, create `.planning/quick/81-invoice-ui-tweaks-and-improved-tuchtzake/81-SUMMARY.md`
</output>
