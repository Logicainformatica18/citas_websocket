import { useEffect, useState } from 'react';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { toast } from 'sonner';

type Survey = { id: number; title: string; [key: string]: any };
const today = () => { const date = new Date(); const month = String(date.getMonth() + 1).padStart(2, '0'); const day = String(date.getDate()).padStart(2, '0'); return `${date.getFullYear()}-${month}-${day}`; };
const empty = () => ({ title: '', description: '', detail: '', password: '', type: 'encuesta', state: '', date_start: today(), date_end: today(), url: '', visible: true, email_confirmation: false, front_page: null as File | null });
const publicSurveyUrl = (survey?: Partial<Survey> | null, customUrl?: string) => {
    const slug = (customUrl ?? survey?.url ?? '').trim();
    const path = slug || String(survey?.id ?? '');
    return `${window.location.origin}/survey/${path}`;
};
export default function SurveyModal({ open, onClose, onSaved, surveyToEdit }: { open: boolean; onClose: () => void; onSaved: (survey: Survey) => void; surveyToEdit: Survey | null }) {
    const [form, setForm] = useState(empty()); const [busy, setBusy] = useState(false);
    const [copied, setCopied] = useState(false);
    useEffect(() => { setForm(surveyToEdit ? { ...empty(), ...surveyToEdit, state: surveyToEdit.state === 'private' ? 'private' : 'public', front_page: null } : empty()); }, [surveyToEdit, open]);
    const change = (event: any) => setForm({ ...form, [event.target.name]: event.target.type === 'checkbox' ? event.target.checked : event.target.value });
    const shareUrl = publicSurveyUrl(surveyToEdit ?? form, form.url || (surveyToEdit?.url ?? ''));
    const copyLink = async () => {
        try {
            await navigator.clipboard.writeText(shareUrl);
            setCopied(true);
            toast.success('Enlace público copiado');
            window.setTimeout(() => setCopied(false), 1500);
        } catch {
            toast.error('No se pudo copiar el enlace');
        }
    };
    const openLink = () => window.open(shareUrl, '_blank', 'noopener,noreferrer');
    const submit = async () => { try { setBusy(true); const data = new FormData(); Object.entries(form).forEach(([key, value]) => { if (value !== null && value !== '') data.append(key, key === 'visible' || key === 'email_confirmation' ? (value ? '1' : '0') : value instanceof File ? value : String(value)); }); if (surveyToEdit) data.append('_method', 'PUT'); const response = await axios.post(surveyToEdit ? `/surveys/${surveyToEdit.id}` : '/surveys', data); onSaved(response.data.survey); toast.success(surveyToEdit ? 'Encuesta actualizada' : 'Encuesta creada'); onClose(); } catch (error: any) { const errors = error?.response?.data?.errors; const messages = errors ? Object.values(errors).flat().map((message: any) => message.replace('The visible field must be true or false.', 'El campo visible debe ser verdadero o falso.').replace('The email confirmation field must be true or false.', 'El campo de confirmación por email debe ser verdadero o falso.')).join(' ') : ''; toast.error(messages || 'No se pudo guardar la encuesta.'); } finally { setBusy(false); } };
    return <Dialog open={open} onOpenChange={(value) => !value && onClose()}><DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"><DialogHeader><DialogTitle>{surveyToEdit ? 'Editar encuesta' : 'Nueva encuesta'}</DialogTitle></DialogHeader><div className="grid gap-4 py-4 sm:grid-cols-2">
        <div><Label>Título *</Label><Input name="title" value={form.title} onChange={change} maxLength={255} /></div><div><Label>Tipo</Label><Input name="type" value={form.type} onChange={change} /></div><div><Label>Descripción</Label><Input name="description" value={form.description} onChange={change} /></div><div><Label>Estado</Label><select name="state" value={form.state} onChange={change} className="w-full rounded-md border bg-background px-3 py-2 text-sm"><option value="public">Público</option><option value="private">Privado</option></select></div><div><Label>Detalle</Label><Input name="detail" value={form.detail} onChange={change} /></div><div><Label>URL</Label><Input name="url" value={form.url} onChange={change} /></div><div><Label>Contraseña</Label><Input name="password" type="password" value={form.password} onChange={change} /></div><div><Label>Imagen</Label><Input name="front_page" type="file" accept="image/*" onChange={(event) => setForm({ ...form, front_page: event.target.files?.[0] || null })} /></div><div><Label>Desde</Label><Input name="date_start" type="date" value={form.date_start || ''} onChange={change} /></div><div><Label>Hasta</Label><Input name="date_end" type="date" value={form.date_end || ''} onChange={change} /></div><label className="flex items-center gap-2"><input name="visible" type="checkbox" checked={form.visible} onChange={change} />Visible</label><label className="flex items-center gap-2"><input name="email_confirmation" type="checkbox" checked={form.email_confirmation} onChange={change} />Confirmación por email</label>
    </div>
    <div className="rounded-lg border border-dashed border-sky-200 bg-sky-50 p-3">
        <div className="mb-2 flex items-center justify-between gap-2">
            <Label>Enlace público</Label>
            <div className="flex gap-2">
                <Button type="button" variant="outline" size="sm" onClick={copyLink}>{copied ? 'Copiado' : 'Copiar'}</Button>
                <Button type="button" variant="secondary" size="sm" onClick={openLink}>Abrir</Button>
            </div>
        </div>
        <Input value={shareUrl} readOnly className="bg-white" />
    </div>
    <DialogFooter><Button variant="outline" onClick={() => setForm(empty())} disabled={busy}>Limpiar</Button><Button onClick={submit} disabled={busy}>{busy ? 'Guardando...' : surveyToEdit ? 'Actualizar' : 'Guardar'}</Button></DialogFooter></DialogContent></Dialog>;
}