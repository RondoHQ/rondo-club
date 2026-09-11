"""Synthetic PDFs only: encryption, malformed input, and resource boundaries."""
import json
import subprocess
import sys
import tempfile
import unittest
from datetime import date
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'vendor/vog-python'))
from pypdf import PdfWriter
from pypdf.generic import DictionaryObject, NameObject, DecodedStreamObject


def fixture(path, encryption=False, pages=1, password=''):
    writer = PdfWriter()
    page = writer.add_blank_page(width=595, height=842)
    font = DictionaryObject({NameObject('/Type'): NameObject('/Font'), NameObject('/Subtype'): NameObject('/Type1'), NameObject('/BaseFont'): NameObject('/Helvetica')})
    page[NameObject('/Resources')] = DictionaryObject({NameObject('/Font'): DictionaryObject({NameObject('/F1'): writer._add_object(font)})})
    today = date.today()
    month = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'][today.month - 1]
    text = '\n'.join([
        'Verklaring Omtrent het Gedrag', f'Date Datum {today.day} {month} {today.year}',
        'Our reference Ons kenmerk 999999999', 'Surname Geslachtsnaam Voorbeeld',
        'Prefix to surname Tussenvoegsels van', 'Given names Voorna(a)m(en) Test',
        'Date of birth Geboortedatum 2 januari 1990',
        'Hierbij geef ik u de VOG die u nodig heeft voor:', 'Vrijwilliger bij Testclub',
        'Er is bij deze screening uitgegaan van het volgende profiel:', '84',
        'Op de volgende pagina staat de algemene legenda.',
    ])
    lines = [line.replace('(', '\\(').replace(')', '\\)') for line in text.splitlines()]
    stream = DecodedStreamObject()
    stream.set_data(('BT /F1 11 Tf 15 TL 40 800 Td ' + ' '.join(f'({line}) Tj T*' for line in lines) + ' ET').encode())
    page[NameObject('/Contents')] = writer._add_object(stream)
    for _ in range(pages - 1):
        writer.add_blank_page(width=595, height=842)
    if encryption:
        writer.encrypt(password, owner_password='synthetic-owner', algorithm='AES-128')
    writer.write(path)


class ReaderTest(unittest.TestCase):
    def run_reader(self, path):
        result = subprocess.run([sys.executable, '-I', str(ROOT / 'bin/vog/read.py'), str(path)], capture_output=True, timeout=12)
        return json.loads(result.stdout)

    def test_original_encrypted_and_plain_text(self):
        with tempfile.TemporaryDirectory() as temp:
            for encrypted in [False, True]:
                path = Path(temp) / 'test.pdf'
                fixture(path, encryption=encrypted)
                original = path.read_bytes()
                result = self.run_reader(path)
                self.assertEqual(1, result['pages'])
                self.assertIn('Testclub', result['text'])
                self.assertEqual(original, path.read_bytes())

    def test_page_limit_password_and_malformed_files(self):
        with tempfile.TemporaryDirectory() as temp:
            path = Path(temp) / 'test.pdf'
            for arguments in [{'pages': 6}, {'encryption': True, 'password': 'not-empty'}]:
                fixture(path, **arguments)
                self.assertEqual('pdf_unreadable', self.run_reader(path)['error'])
            path.write_bytes(b'%PDF-invalid')
            self.assertEqual('pdf_unreadable', self.run_reader(path)['error'])


if __name__ == '__main__':
    unittest.main()
