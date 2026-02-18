---
phase: quick-79
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - includes/class-invoice-email-sender.php
  - includes/class-finance-config.php
  - includes/class-rest-api.php
  - src/pages/Finance/FinanceSettings.jsx
autonomous: true
must_haves:
  truths:
    - "Invoice emails arrive as HTML with styled content, not plain text"
    - "QR code is displayed inline in the email body (not as a separate attachment)"
    - "PDF invoice remains as a downloadable attachment"
    - "Email template in settings uses the existing Tiptap RichTextEditor"
    - "The {qr_code} variable is available and documented in the variables info box"
    - "Header reads 'Template e-mail voor boetes' instead of 'E-mailsjabloon'"
  artifacts:
    - path: "includes/class-invoice-email-sender.php"
      provides: "HTML email sending with inline CID QR code"
      contains: "Content-Type: text/html"
    - path: "includes/class-finance-config.php"
      provides: "HTML default email template with {qr_code} variable"
      contains: "{qr_code}"
    - path: "includes/class-rest-api.php"
      provides: "wp_kses_post sanitization for email_template"
      contains: "wp_kses_post"
    - path: "src/pages/Finance/FinanceSettings.jsx"
      provides: "Rich text editor for email template, renamed header"
      contains: "RichTextEditor"
  key_links:
    - from: "includes/class-invoice-email-sender.php"
      to: "includes/class-finance-config.php"
      via: "get_email_template() returns HTML"
      pattern: "get_email_template"
    - from: "src/pages/Finance/FinanceSettings.jsx"
      to: "src/components/RichTextEditor.jsx"
      via: "import and render for email_template field"
      pattern: "RichTextEditor"
---

<objective>
Convert the invoice email system from plain text to HTML emails with inline QR code embedding, and upgrade the email template editor from a plain textarea to the existing Tiptap RichTextEditor.

Purpose: Recipients get a professional HTML email with the QR code visible inline (no need to open a separate attachment), making the payment flow more intuitive.
Output: Modified PHP email sender, updated default HTML template, rich text editor in settings UI, proper HTML sanitization in REST API.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@includes/class-invoice-email-sender.php
@includes/class-finance-config.php
@includes/class-rest-api.php
@src/pages/Finance/FinanceSettings.jsx
@src/components/RichTextEditor.jsx
</context>

<tasks>

<task type="auto">
  <name>Task 1: Convert email to HTML with inline CID QR code</name>
  <files>includes/class-invoice-email-sender.php, includes/class-finance-config.php, includes/class-rest-api.php</files>
  <action>
  **InvoiceEmailSender (class-invoice-email-sender.php):**

  1. Add `Content-Type: text/html; charset=UTF-8` to the `$headers` array (alongside the existing From and optional Bcc headers).

  2. For the QR code: instead of adding it to `$attachments`, embed it as an inline CID image:
     - Read the QR code file with `file_get_contents()`
     - Generate a unique Content-ID like `qr-{invoice_number}@rondo`
     - Use `phpmailer_init` action hook (WordPress hook that gives access to the PHPMailer instance before sending) to add the QR code as an inline embedded image via `$phpmailer->addStringEmbeddedImage($qr_data, $cid, 'qr-code.png', 'base64', 'image/png')`
     - Add the hook right before `wp_mail()` call and remove it right after (use a closure stored in a variable so it can be removed with `remove_action`)
     - In the template variable replacement, add `{qr_code}` which gets replaced with `<img src="cid:{cid}" alt="QR Code betaallink" width="200" style="display:block;" />`
     - If no QR code exists for the invoice, replace `{qr_code}` with an empty string
     - Remove the QR code from the `$attachments` array (keep only PDF)

  3. The `{tuchtzaken_lijst}` variable currently builds plain text with `\n` line breaks. Convert this to use HTML: wrap each line item in `<li>` tags and wrap the whole list in `<ul style="margin:0;padding-left:20px;">`. Each item: `<li>{match_desc}: {sanction_desc} — {formatted_amount}</li>`. If no items, set to empty string.

  4. The `{betaallink}` replacement currently outputs a raw URL. Change the replacement to output a styled HTML link: `<a href="{url}" style="color:#0891b2;text-decoration:underline;">{url}</a>`. Keep the fallback text "Neem contact op voor betaalinformatie." as plain text (no link).

  **FinanceConfig (class-finance-config.php):**

  5. Update the `DEFAULTS['email_template']` to be an HTML template. Create a clean, responsive HTML email template:
     ```html
     <div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;">
       <p>Beste {naam},</p>
       <p>Bijgevoegd vindt u de factuur {factuur_nummer} voor opgelegde boetes vanuit de tuchtcommissie.</p>
       {tuchtzaken_lijst}
       <p>Het totaalbedrag is <strong>{totaal_bedrag}</strong>.</p>
       <p>U kunt betalen via de volgende link: {betaallink}</p>
       {qr_code}
       <p>Met vriendelijke groet,<br/>{organisatie_naam}</p>
     </div>
     ```
     Use inline CSS only (email clients don't support external/head CSS). Keep it simple and professional.

  **REST API (class-rest-api.php):**

  6. Change the `email_template` sanitize_callback from `'sanitize_textarea_field'` to `'wp_kses_post'` on line 748. This allows safe HTML tags (p, strong, em, a, ul, ol, li, br, img, div, span, h1-h6, table, etc.) while stripping dangerous content (script, iframe, etc.).
  </action>
  <verify>
  - `grep -n "Content-Type.*text/html" includes/class-invoice-email-sender.php` shows the header
  - `grep -n "addStringEmbeddedImage\|phpmailer_init" includes/class-invoice-email-sender.php` shows inline CID embedding
  - `grep -n "qr_code" includes/class-invoice-email-sender.php` shows the {qr_code} variable replacement
  - `grep -n "qr_code" includes/class-finance-config.php` shows the variable in default template
  - `grep -n "wp_kses_post" includes/class-rest-api.php` shows updated sanitization
  - `npm run build` compiles without errors
  </verify>
  <done>
  - Invoice emails are sent as text/html with charset=UTF-8
  - QR code is embedded inline via CID (not as attachment), PDF remains as attachment
  - {qr_code} template variable produces an inline img tag referencing the CID
  - {tuchtzaken_lijst} renders as an HTML unordered list
  - {betaallink} renders as a clickable HTML link
  - Default template is clean HTML with inline styles
  - REST API accepts HTML content via wp_kses_post sanitization
  </done>
</task>

<task type="auto">
  <name>Task 2: Replace textarea with RichTextEditor and rename header</name>
  <files>src/pages/Finance/FinanceSettings.jsx</files>
  <action>
  In `src/pages/Finance/FinanceSettings.jsx`:

  1. Add import at top: `import RichTextEditor from '@/components/RichTextEditor';`

  2. Rename the header on line 550 from `E-mailsjabloon` to `Template e-mail voor boetes`.

  3. Replace the textarea block (lines 560-566) with the RichTextEditor component:
     ```jsx
     <RichTextEditor
       value={formData.email_template}
       onChange={(html) => setFormData(prev => ({ ...prev, email_template: html }))}
       placeholder="Schrijf hier het e-mailsjabloon..."
       minHeight="200px"
     />
     ```
     Remove the `<label htmlFor="email_template">E-mailtekst</label>` since the RichTextEditor is self-explanatory in context, or keep it but remove the `htmlFor` since RichTextEditor doesn't use a native input.

  4. In the variables info box (lines 570-580), add the new `{qr_code}` variable:
     ```jsx
     <div><code>{'{qr_code}'}</code> - QR-code afbeelding (betaallink)</div>
     ```
     Add this after the `{betaallink}` line and before `{organisatie_naam}`.

  5. Add a note below the variables box explaining that variables should be placed as plain text in the editor and will be replaced when the email is sent:
     ```jsx
     <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
       Typ de variabelen als tekst in de editor. Ze worden automatisch vervangen bij het versturen.
     </p>
     ```
  </action>
  <verify>
  - `npm run build` succeeds without errors
  - `grep -n "RichTextEditor" src/pages/Finance/FinanceSettings.jsx` shows import and usage
  - `grep -n "Template e-mail voor boetes" src/pages/Finance/FinanceSettings.jsx` shows renamed header
  - `grep -n "qr_code" src/pages/Finance/FinanceSettings.jsx` shows new variable documentation
  </verify>
  <done>
  - Header reads "Template e-mail voor boetes" (not "E-mailsjabloon")
  - Email template field uses Tiptap RichTextEditor (not plain textarea)
  - {qr_code} variable is documented in the info box
  - Build passes cleanly
  </done>
</task>

</tasks>

<verification>
- `npm run build` passes without errors
- `grep -rn "sanitize_textarea_field.*email_template" includes/class-rest-api.php` returns NO results (old sanitization removed)
- `grep -rn "wp_kses_post" includes/class-rest-api.php` returns the email_template line
- `grep -rn "text/html" includes/class-invoice-email-sender.php` confirms HTML content type
- `grep -rn "phpmailer_init\|addStringEmbeddedImage" includes/class-invoice-email-sender.php` confirms inline CID approach
- `grep -rn "RichTextEditor" src/pages/Finance/FinanceSettings.jsx` confirms WYSIWYG editor usage
</verification>

<success_criteria>
- Invoice emails are sent as HTML with inline QR code via CID embedding
- PDF remains as a regular file attachment
- Email template default is clean HTML
- Settings UI shows Tiptap rich text editor instead of textarea
- Header renamed to "Template e-mail voor boetes"
- {qr_code} variable available and documented
- REST API uses wp_kses_post for HTML sanitization
- Build compiles without errors
</success_criteria>

<output>
After completion, create `.planning/quick/79-html-invoice-email-with-inline-qr-code-r/79-SUMMARY.md`
</output>
