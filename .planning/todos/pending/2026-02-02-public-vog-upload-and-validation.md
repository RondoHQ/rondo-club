---
created: 2026-02-02T13:50
title: Public VOG Upload and Validation
area: api
files: []
---

## Problem

We need to provide a publicly accessible, secure way for people to upload their received VOGs (Verklaring Omtrent het Gedrag). Currently, there is no straightforward method for them to do this without logging in, and we need to ensure the uploaded document is correctly and securely tied to the individual.

## Solution

1.  **Public Upload Form**: Create a publicly accessible web form where a user can upload a file.
2.  **Unique Access Links**: Generate a unique, secure link (containing a hash) for each person. This link will lead them to the upload form and will be used to identify them.
3.  **API Validation**: Upon upload, use the [validatie.nl API](https://www.validatie.nl/static/files/API-specificatie%20GAAV%20v1.0.pdf) to validate the VOG document.
4.  **Date Extraction**: If the VOG is validated successfully, extract the creation date from the PDF document.
5.  **System Update**: Use the extracted date to update the `datum-vog` field for the corresponding person in our system.
6.  **Data Sync**: Sync the updated `datum-vog` back to Sportlink.
