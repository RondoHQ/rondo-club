import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { prmApi } from '@/api/client';

export function useCreditDraft(payload) {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [preview, setPreview] = useState(null);
  const [requestId, setRequestId] = useState(() => crypto.randomUUID());
  const [error, setError] = useState('');
  const calculate = useMutation({ mutationFn: payload => prmApi.previewCredit(payload).then(res => res.data) });
  const create = useMutation({ mutationFn: payload => prmApi.createCredit(payload).then(res => res.data) });
  const pending = calculate.isPending || create.isPending;
  const reset = () => {
    setPreview(null);
    setError('');
    setRequestId(crypto.randomUUID());
  };
  const submit = async event => {
    event.preventDefault();
    setError('');
    try {
      if (!preview) {
        setPreview(await calculate.mutateAsync(payload));
        return;
      }
      const result = await create.mutateAsync({ ...payload, expected_amount: preview.amount, request_id: requestId });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['invoices'] }),
        queryClient.invalidateQueries({ queryKey: ['invoice'] }),
      ]);
      navigate(`/financien/facturen/${result.id}`);
    } catch (err) {
      if (err.response?.status === 409) { setPreview(null); setRequestId(crypto.randomUUID()); }
      setError(err.response?.data?.message || 'De creditnota kon niet worden verwerkt. Probeer opnieuw.');
    }
  };
  return { preview, pending, error, reset, submit };
}

