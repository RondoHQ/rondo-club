import { useRef, useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { BookUser } from 'lucide-react';
import { wpApi } from '@/api/client';
import { fetchAllFilteredPeople, peopleKeys } from '@/hooks/usePeople';
import { invoiceContactPayload, invoiceContactSignature, invoiceCustomerFromContact } from '@/utils/invoiceContact';

export default function InvoiceContactSave({ customer, disabled = false }) {
  const queryClient = useQueryClient();
  const savingRef = useRef(false);
  const [saved, setSaved] = useState(null);
  const [error, setError] = useState('');
  const signature = invoiceContactSignature(customer);
  const isSaved = saved?.signature === signature;
  const save = useMutation({
    retry: false,
    mutationFn: async (snapshot) => {
      const contacts = await fetchAllFilteredPeople({ personType: 'contact' });
      const existing = contacts.find((contact) => invoiceContactSignature(invoiceCustomerFromContact(contact)) === invoiceContactSignature(snapshot));
      if (existing) return existing;
      return (await wpApi.createPerson(invoiceContactPayload(snapshot))).data;
    },
    onSuccess: (contact, snapshot) => {
      setSaved({ signature: invoiceContactSignature(snapshot) });
      queryClient.invalidateQueries({ queryKey: peopleKeys.all });
      queryClient.invalidateQueries({ queryKey: ['dashboard'] });
    },
    onError: (requestError) => {
      setError(requestError.response?.data?.message || 'Opslaan in het adresboek is niet gelukt. Controleer eerst via ‘Kies uit adresboek’ of het contact al is opgeslagen voordat je opnieuw probeert.');
    },
    onSettled: () => { savingRef.current = false; },
  });

  const handleSave = () => {
    if (savingRef.current || isSaved) return;
    setError('');
    const emailInput = document.createElement('input');
    emailInput.type = 'email';
    if (!customer.customerName.trim() || !customer.customerAddress.trim()) {
      setError('Vul een klantnaam en adres in om het contact op te slaan.');
      return;
    }
    for (const email of [customer.customerEmail, customer.customerCcEmail]) {
      emailInput.value = email.trim();
      if (!emailInput.checkValidity()) {
        setError('Vul een geldig e-mailadres in bij E-mail en CC, of laat het veld leeg.');
        return;
      }
    }
    savingRef.current = true;
    save.mutate({ ...customer });
  };

  return (
    <div className="md:col-span-2 space-y-2">
      <button type="button" className="btn-secondary gap-2" disabled={disabled || save.isPending || isSaved} onClick={handleSave}>
        <BookUser className="w-4 h-4" aria-hidden="true" />
        {save.isPending ? 'Opslaan…' : isSaved ? 'Opgeslagen in adresboek' : 'Opslaan in adresboek'}
      </button>
      <p className="text-sm text-gray-600 dark:text-gray-300">Bewaar deze klantgegevens als extern contact om ze bij volgende facturen opnieuw te kiezen.</p>
      {isSaved && <p role="status" className="text-sm text-green-700 dark:text-green-300">De klantgegevens staan in het adresboek en zijn beschikbaar via ‘Kies uit adresboek’.</p>}
      {error && <p role="alert" className="text-sm text-red-700 dark:text-red-300">{error}</p>}
    </div>
  );
}
