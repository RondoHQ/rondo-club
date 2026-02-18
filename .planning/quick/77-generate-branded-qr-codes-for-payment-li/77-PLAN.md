---
phase: quick-77
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - composer.json
  - includes/class-qr-code-generator.php
  - includes/class-rest-invoices.php
  - functions.php
autonomous: true

must_haves:
  truths:
    - "Mollie invoices get a QR code generated from the payment URL after payment link creation"
    - "QR code uses club accent color (from FinanceConfig::get_accent_color()) for module pixels"
    - "QR code has club logo overlaid in the center when a logo is configured"
    - "QR code is saved as qr-{invoice_number}.png in wp-content/uploads/invoices/"
    - "qr_code_path ACF field is set on the invoice after QR generation"
    - "clear_qr_code() is no longer called when Mollie is the active provider"
    - "QR codes appear in the PDF and on the invoice detail page (existing wiring unchanged)"
  artifacts:
    - path: "includes/class-qr-code-generator.php"
      provides: "Provider-agnostic branded QR code generation"
      exports: ["QrCodeGenerator::generate(string $url, int $invoice_id): string|WP_Error"]
    - path: "composer.json"
      provides: "chillerlan/php-qrcode ^5.0 dependency"
      contains: "chillerlan/php-qrcode"
  key_links:
    - from: "includes/class-rest-invoices.php (send_invoice + regenerate_payment_link)"
      to: "QrCodeGenerator::generate()"
      via: "called after MolliePayment::create_payment_link() returns a URL"
    - from: "QrCodeGenerator::generate()"
      to: "FinanceConfig"
      via: "get_accent_color() and get_club_logo_id() for branding"
---

<objective>
Generate branded QR codes for Mollie payment links using the club's accent color and logo.

Purpose: Mollie invoices currently have no QR code — recipients cannot scan to pay. Adding branded QR codes makes Mollie invoices functionally equivalent to Rabobank invoices and improves the payment UX.
Output: New QrCodeGenerator class + wiring into the Mollie payment flow.
</objective>

<execution_context>
@/Users/joostdevalk/.claude/get-shit-done/workflows/execute-plan.md
@/Users/joostdevalk/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/quick/77-generate-branded-qr-codes-for-payment-li/77-PLAN.md
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add chillerlan/php-qrcode library and create QrCodeGenerator class</name>
  <files>
    composer.json
    includes/class-qr-code-generator.php
    functions.php
  </files>
  <action>
**Step 1 — Add library to composer.json:**

Add `"chillerlan/php-qrcode": "^5.0"` to the `require` block in `composer.json` (keep packages alphabetically sorted). Then run:

```bash
cd /Users/joostdevalk/Code/rondo/rondo-club && composer require chillerlan/php-qrcode:^5.0
```

**Step 2 — Create `includes/class-qr-code-generator.php`:**

Namespace: `Rondo\Finance`. Class: `QrCodeGenerator`.

Implement one public static method:

```php
public static function generate( string $url, int $invoice_id ): string|\WP_Error
```

Implementation details:

1. Load accent color: `$finance_config = new \Rondo\Config\FinanceConfig(); $accent_hex = $finance_config->get_accent_color();`
   - Default to `'#0891b2'` if empty.
   - Convert hex to RGB integers for use with chillerlan options.

2. Build QR options using `chillerlan\QRCode\QROptions`:
   - `outputType`: `QROutputInterface::GDIMAGE_PNG` — raster PNG output via GD.
   - `imageBase64`: `false` — return raw PNG bytes.
   - `scale`: `10` — 10px per module (produces ~330×330px for a typical QR).
   - `addQuietzone`: `true`, `quietzoneSize`: `4`.
   - `moduleValues`: Set `QRMatrix::M_DATA` and `QRMatrix::M_FINDER` and `QRMatrix::M_ALIGNMENT` modules to the accent color RGB. Dark modules use accent color; light modules use white (`[255, 255, 255]`). Use `QRMatrix::M_DATA_DARK`, `QRMatrix::M_FINDER_DARK`, `QRMatrix::M_ALIGNMENT_DARK`, `QRMatrix::M_TIMING_DARK`, `QRMatrix::M_DARKMODULE`, `QRMatrix::M_FORMAT_DARK`, `QRMatrix::M_VERSION_DARK` — all map to accent RGB. Light variants map to `[255, 255, 255]`.
   - `imageTransparent`: `false`.

   **Note on chillerlan v5 API:** In v5, `QROptions` properties are set directly (not via array constructor). Use `$options->outputType`, `$options->scale`, etc. Check vendor source if exact constants differ. The module color approach uses `$options->moduleValues` as an array keyed by matrix constants.

   **Alternative simpler approach if moduleValues is complex:** Use `$options->markupDark` / `$options->markupLight` are not available for GD output. Instead, after generating a plain white-on-dark QR, use GD `imagecolorallocate` to re-color pixels — but this is fragile. **Prefer the moduleValues approach.**

   **Practical implementation using GD output with color:**
   - Use `QROutputInterface::GDIMAGE_PNG`
   - Set `$options->imageBase64 = false`
   - GD output in chillerlan v5 supports `$options->moduleValues` for custom colors per module type
   - Key the moduleValues array by the integer constants from `QRMatrix` (e.g. `QRMatrix::M_DATA_DARK => [r, g, b]`)

3. Generate QR: `$qrcode = new \chillerlan\QRCode\QRCode($options); $png_data = $qrcode->render($url);`

4. Logo overlay (only if configured):
   - `$logo_id = $finance_config->get_club_logo_id();`
   - If `$logo_id > 0`: `$logo_path = get_attached_file($logo_id);`
   - Only overlay if file exists and is a supported image (PNG/JPG via `getimagesize`).
   - Use GD: `$qr_image = imagecreatefromstring($png_data)`. Get QR width/height.
   - Load logo: `imagecreatefromjpeg` or `imagecreatefrompng` based on MIME type.
   - Resize logo to 20% of QR dimensions, centered.
   - Copy logo onto QR image using `imagecopyresampled`.
   - Capture PNG: `ob_start(); imagepng($qr_image); $png_data = ob_get_clean();`
   - Destroy GD resources.

5. Save to file:
   - `$upload_dir = wp_upload_dir();`
   - `$invoices_dir = $upload_dir['basedir'] . '/invoices';`
   - `wp_mkdir_p($invoices_dir);` if not exists.
   - `$invoice_number = get_field('invoice_number', $invoice_id);`
   - `$filename = 'qr-' . $invoice_number . '.png';`
   - `$full_path = $invoices_dir . '/' . $filename;`
   - `file_put_contents($full_path, $png_data);`
   - `update_field('qr_code_path', 'invoices/' . $filename, $invoice_id);`
   - Return `'invoices/' . $filename`.

6. Wrap entire method in try/catch — return `WP_Error('qr_generation_failed', ...)` on exception.

**Step 3 — Register class in `functions.php`:**

Add `use Rondo\Finance\QrCodeGenerator;` in the PSR-4 imports section (after `MollieWebhook`, line ~79). No instantiation needed — it is a static utility class. No need to add it to `rondo_init()`.
  </action>
  <verify>
```bash
cd /Users/joostdevalk/Code/rondo/rondo-club && composer dump-autoload && php -r "require 'vendor/autoload.php'; echo class_exists('chillerlan\QRCode\QRCode') ? 'OK' : 'MISSING';"
```
Should output `OK`.
  </verify>
  <done>chillerlan/php-qrcode is installed, QrCodeGenerator class exists in includes/ with a static generate() method that takes a URL + invoice_id, applies accent color + logo overlay, saves PNG to uploads/invoices/, updates qr_code_path field, and returns the relative path.</done>
</task>

<task type="auto">
  <name>Task 2: Wire QrCodeGenerator into Mollie flow and remove clear_qr_code() calls</name>
  <files>
    includes/class-rest-invoices.php
  </files>
  <action>
There are two locations in `class-rest-invoices.php` where Mollie currently calls `$this->clear_qr_code($invoice_id)`. Replace both with QR generation.

**Location 1 — `send_invoice()` method (~line 711-714):**

Before change:
```php
if ( 'mollie' === $active_provider ) {
    // Clear any existing QR code (Mollie doesn't provide QR codes)
    $this->clear_qr_code( $invoice_id );

    $mollie_payment = new MolliePayment();
    $payment_result = $mollie_payment->create_payment_link( $invoice_id );
    if ( is_wp_error( $payment_result ) ) {
        error_log( 'Mollie payment link creation failed for invoice ' . $invoice_id . ': ' . $payment_result->get_error_message() );
    }
}
```

After change:
```php
if ( 'mollie' === $active_provider ) {
    $mollie_payment = new MolliePayment();
    $payment_result = $mollie_payment->create_payment_link( $invoice_id );
    if ( is_wp_error( $payment_result ) ) {
        error_log( 'Mollie payment link creation failed for invoice ' . $invoice_id . ': ' . $payment_result->get_error_message() );
    } elseif ( ! empty( $payment_result ) ) {
        // Generate branded QR code from payment URL (non-blocking)
        $qr_result = \Rondo\Finance\QrCodeGenerator::generate( $payment_result, $invoice_id );
        if ( is_wp_error( $qr_result ) ) {
            error_log( 'QR code generation failed for invoice ' . $invoice_id . ': ' . $qr_result->get_error_message() );
        }
    }
}
```

**Location 2 — `regenerate_payment_link()` method (~line 857-866):**

Before change:
```php
if ( 'mollie' === $active_provider ) {
    // Clear Mollie payment ID to bypass idempotency and force a new payment link
    delete_post_meta( $invoice_id, '_mollie_payment_id' );
    update_field( 'payment_link', '', $invoice_id );

    // Clear any existing QR code (Mollie doesn't provide QR codes)
    $this->clear_qr_code( $invoice_id );

    $mollie_payment = new MolliePayment();
    $result         = $mollie_payment->create_payment_link( $invoice_id );
    if ( is_wp_error( $result ) ) {
        return $result;
    }
}
```

After change:
```php
if ( 'mollie' === $active_provider ) {
    // Clear Mollie payment ID to bypass idempotency and force a new payment link
    delete_post_meta( $invoice_id, '_mollie_payment_id' );
    update_field( 'payment_link', '', $invoice_id );

    $mollie_payment = new MolliePayment();
    $result         = $mollie_payment->create_payment_link( $invoice_id );
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    // Generate branded QR code from new payment URL (non-blocking)
    if ( ! empty( $result ) ) {
        $qr_result = \Rondo\Finance\QrCodeGenerator::generate( $result, $invoice_id );
        if ( is_wp_error( $qr_result ) ) {
            error_log( 'QR code generation failed for invoice ' . $invoice_id . ': ' . $qr_result->get_error_message() );
        }
    }
}
```

**Note:** The `clear_qr_code()` method itself should be kept — it is still used in `reset_payment_state()` which deliberately wipes all payment data. Do NOT remove the method or that call.

After edits, run the frontend build to verify no compilation errors (the changes are PHP-only, but running build confirms theme integrity):
```bash
cd /Users/joostdevalk/Code/rondo/rondo-club && npm run build 2>&1 | tail -5
```
  </action>
  <verify>
```bash
cd /Users/joostdevalk/Code/rondo/rondo-club && php -l includes/class-rest-invoices.php && php -l includes/class-qr-code-generator.php
```
Both should output `No syntax errors detected`.

Also verify clear_qr_code is gone from Mollie paths but still present for reset:
```bash
grep -n "clear_qr_code" /Users/joostdevalk/Code/rondo/rondo-club/includes/class-rest-invoices.php
```
Should show it is only called in `reset_payment_state()` (line ~961) and defined at `private function clear_qr_code` — NOT in `send_invoice` or `regenerate_payment_link`.
  </verify>
  <done>Mollie payment flow generates a branded QR code after each successful payment link creation. clear_qr_code() is no longer called during Mollie send or regenerate operations. reset_payment_state() still clears QR codes as intended. PHP syntax is valid.</done>
</task>

</tasks>

<verification>
1. PHP syntax checks pass for both modified/new class files.
2. `chillerlan/php-qrcode` appears in `composer.json` require block and in `vendor/`.
3. `grep -n "clear_qr_code" includes/class-rest-invoices.php` shows the call only in `reset_payment_state()`.
4. `grep -n "QrCodeGenerator" includes/class-rest-invoices.php` shows generate() called in both Mollie branches.
5. After deploying and sending a Mollie invoice, a `qr-{invoice_number}.png` file appears in `wp-content/uploads/invoices/` and the PDF embeds it.
</verification>

<success_criteria>
- chillerlan/php-qrcode installed and autoloaded
- QrCodeGenerator::generate() produces a PNG with accent-colored modules and optional logo overlay
- Mollie invoices get QR codes set on send and regenerate operations
- clear_qr_code() not called during Mollie payment link creation (only on explicit reset)
- Existing Rabobank QR flow unchanged
- PHP lint passes on all modified files
</success_criteria>

<output>
After completion, create `.planning/quick/77-generate-branded-qr-codes-for-payment-li/77-SUMMARY.md` following the summary template.
</output>
