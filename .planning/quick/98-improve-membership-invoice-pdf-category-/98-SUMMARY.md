# Quick Task 98: Summary

## What was done

### BulkInvoiceCreator (`includes/class-bulk-invoice-creator.php`)
- Line item description now includes category label: "Contributie - Pupillen" instead of "Contributie 2025-2026"
- Looks up label via `get_categories_for_season()` with slug fallback

### InvoicePdfGenerator (`includes/class-invoice-pdf-generator.php`)
- Reads `invoice_type` and `payment_link` from invoice post
- **Membership invoices** get a clean 2-column layout (Omschrijving + Bedrag)
- **Discipline invoices** keep the 4-column layout (Omschrijving + Kaart + Schorsing + Bedrag)
- **Membership invoices** omit "Vervaldatum" from the invoice meta section
- **Membership Betaalgegevens**: "Je ontvangt per e-mail een betaallink waarmee je direct kunt betalen of een betaalplan kunt kiezen." + QR code
- **Discipline Betaalgegevens**: IBAN + payment clause + QR code (unchanged)

### Production
- Invoices 6188 (2026C001) and 6189 (2026C002) updated with new line items and regenerated PDFs
- QR code generated for 6188's payment link

## Commits
- `46765a91` — feat(quick-98): improve membership invoice PDF layout
