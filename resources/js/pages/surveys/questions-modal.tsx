import { useEffect, useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Textarea from '@/components/ui/textarea';
import axios from 'axios';
import { toast } from 'sonner';
import { CircleHelp, ListPlus, Trash2 } from 'lucide-react';

const questionTypes = [
    { value: 'short_answer', label: 'Respuesta corta' },
    { value: 'number', label: 'Número' },
    { value: 'email', label: 'Correo electrónico' },
    { value: 'date', label: 'Fecha' },
    { value: 'file', label: 'Archivo' },
    { value: 'multiple_option', label: 'Varias opciones' },
    { value: 'selection', label: 'Selección' },
];

const newForm = () => ({
    title: '', question: '', detail: '', detail_2: '', detail_3: '',
    type: 'short_answer', correct: '', point: '', requerid: 'not',
    evaluate: 'not', selection_id: '', category: 'all', enumeration: '0',
    visible: 'yes', options: [''],
});

type FormState = ReturnType<typeof newForm>;
type Props = { open: boolean; surveyId: number; questionToEdit: any; selections: any[]; onClose: () => void; onSaved: (question: any) => void };

const textValue = (value: unknown) => value == null ? '' : String(value);

export default function QuestionsModal({ open, surveyId, questionToEdit, selections, onClose, onSaved }: Props) {
    const [form, setForm] = useState<FormState>(newForm());
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        setForm(questionToEdit ? {
            ...newForm(),
            title: textValue(questionToEdit.title),
            question: textValue(questionToEdit.question),
            detail: textValue(questionToEdit.detail),
            detail_2: textValue(questionToEdit.detail_2),
            detail_3: textValue(questionToEdit.detail_3),
            type: textValue(questionToEdit.type) || 'short_answer',
            correct: textValue(questionToEdit.correct),
            point: textValue(questionToEdit.point),
            requerid: textValue(questionToEdit.requerid) || 'not',
            evaluate: textValue(questionToEdit.evaluate) || 'not',
            selection_id: questionToEdit.selection_id == null ? '' : String(questionToEdit.selection_id),
            category: textValue(questionToEdit.category) || 'all',
            enumeration: textValue(questionToEdit.enumeration) || '0',
            visible: textValue(questionToEdit.visible) || 'yes',
            options: Array.isArray(questionToEdit.option) && questionToEdit.option.length
                ? questionToEdit.option.map(textValue)
                : [''],
        } : newForm());
    }, [questionToEdit, open]);

    const change = (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => setForm({ ...form, [event.target.name]: event.target.value });
    const updateOption = (index: number, value: string) => setForm({ ...form, options: form.options.map((option, optionIndex) => optionIndex === index ? value : option) });

    const submit = async () => {
        try {
            setBusy(true);
            const data = new FormData();
            Object.entries(form).forEach(([key, value]) => { if (key !== 'options' && value !== '') data.append(key, String(value)); });
            if (form.type === 'multiple_option') form.options.filter((option) => option.trim()).forEach((option) => data.append('option[]', option.trim()));
            if (form.type === 'selection' && form.selection_id) data.append('selection_id', form.selection_id);
            if (questionToEdit) data.append('_method', 'PUT');
            const response = await axios.post(questionToEdit ? `/questions/${questionToEdit.id}` : `/surveys/${surveyId}/questions`, data);
            onSaved(response.data.question);
            toast.success(questionToEdit ? 'Pregunta actualizada' : 'Pregunta creada');
            onClose();
        } catch (error: any) {
            toast.error(Object.values(error?.response?.data?.errors || {}).flat().join(' ') || 'No se pudo guardar la pregunta.');
        } finally { setBusy(false); }
    };

    const typeLabel = questionTypes.find((type) => type.value === form.type)?.label;
    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{questionToEdit ? 'Editar pregunta' : 'Nueva pregunta'}</DialogTitle>
                    <DialogDescription>Define el enunciado y el tipo de respuesta que verá la persona encuestada.</DialogDescription>
                </DialogHeader>
                <div className="space-y-6 py-2">
                    <section className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-[1fr_220px]">
                            <div className="space-y-2"><Label htmlFor="title">Título interno</Label><Input id="title" name="title" value={form.title} onChange={change} maxLength={255} placeholder="Ej. Datos personales" /></div>
                            <div className="space-y-2"><Label htmlFor="type">Tipo de pregunta *</Label><select id="type" name="type" value={form.type} onChange={change} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="">Elegir tipo</option>{questionTypes.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}</select></div>
                        </div>
                        <div className="space-y-2"><Label htmlFor="question">Pregunta *</Label><Textarea id="question" name="question" value={form.question} onChange={change} rows={3} maxLength={255} className="w-full" placeholder="Escribe el enunciado de la pregunta" /></div>
                        <div className="space-y-2"><Label htmlFor="detail">Detalle o ayuda</Label><Textarea id="detail" name="detail" value={form.detail} onChange={change} rows={2} maxLength={255} className="w-full" placeholder="Texto opcional que acompaña a la pregunta" /></div>
                    </section>
                    <section className="rounded-lg border bg-muted/30 p-4">
                        <div className="mb-4 flex items-center gap-2"><CircleHelp size={18} /><div><h3 className="font-medium">Configuración</h3><p className="text-xs text-muted-foreground">Controla cómo se presenta y evalúa la pregunta.</p></div></div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2"><Label htmlFor="detail_2">Detalle 2</Label><Input id="detail_2" name="detail_2" value={form.detail_2} onChange={change} maxLength={255} /></div>
                            <div className="space-y-2"><Label htmlFor="detail_3">Detalle 3</Label><Input id="detail_3" name="detail_3" value={form.detail_3} onChange={change} maxLength={255} /></div>
                            <div className="space-y-2"><Label htmlFor="requerid">¿Es obligatoria?</Label><select id="requerid" name="requerid" value={form.requerid} onChange={change} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="yes">Sí</option><option value="not">No</option></select></div>
                            <div className="space-y-2"><Label htmlFor="evaluate">¿Se evalúa?</Label><select id="evaluate" name="evaluate" value={form.evaluate} onChange={change} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="yes">Sí</option><option value="not">No</option></select></div>
                            <div className="space-y-2"><Label htmlFor="point">Puntaje</Label><Input id="point" name="point" value={form.point} onChange={change} placeholder="Ej. 10" /></div>
                            <div className="space-y-2"><Label htmlFor="category">Categoría para reporte</Label><select id="category" name="category" value={form.category} onChange={change} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="all">Todas</option>{[1, 2, 3, 4].map((category) => <option key={category} value={category}>Categoría {category}</option>)}</select></div>
                            <div className="space-y-2"><Label htmlFor="enumeration">Enumeración</Label><Input id="enumeration" name="enumeration" type="number" min="0" value={form.enumeration} onChange={change} /></div>
                            <div className="space-y-2"><Label htmlFor="visible">Visibilidad</Label><select id="visible" name="visible" value={form.visible} onChange={change} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="yes">Visible</option><option value="not">Oculta</option></select></div>
                        </div>
                    </section>
                    {form.type === 'multiple_option' && <section className="rounded-lg border border-blue-200 bg-blue-50/50 p-4 dark:border-blue-900 dark:bg-blue-950/20"><div className="mb-3"><h3 className="font-medium">Opciones de respuesta</h3><p className="text-xs text-muted-foreground">Agrega entre 1 y 10 alternativas. Los campos vacíos no se guardan.</p></div><div className="space-y-2">{form.options.map((option, index) => <div key={index} className="flex items-center gap-2"><span className="w-6 text-center text-sm text-muted-foreground">{index + 1}</span><Input value={option} onChange={(event) => updateOption(index, event.target.value)} placeholder={`Alternativa ${index + 1}`} maxLength={255} /><Button type="button" variant="ghost" size="icon" onClick={() => setForm({ ...form, options: form.options.filter((_, optionIndex) => optionIndex !== index) })} disabled={form.options.length === 1} aria-label="Quitar opción"><Trash2 size={16} /></Button></div>)}</div><Button type="button" variant="outline" className="mt-3" onClick={() => form.options.length < 10 && setForm({ ...form, options: [...form.options, ''] })} disabled={form.options.length >= 10}><ListPlus size={16} className="mr-2" />Agregar opción</Button><div className="mt-4 space-y-2"><Label htmlFor="correct">Alternativa correcta</Label><select id="correct" name="correct" value={form.correct} onChange={change} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="">Sin respuesta correcta</option>{form.options.filter((option) => option.trim()).map((option, index) => <option key={`${option}-${index}`} value={option}>{option}</option>)}</select></div></section>}
                    {form.type === 'selection' && <section className="rounded-lg border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900 dark:bg-emerald-950/20"><h3 className="font-medium">Lista de selección</h3><p className="mb-3 text-xs text-muted-foreground">Elige la lista que se mostrará como opciones para responder.</p><select name="selection_id" value={form.selection_id} onChange={change} className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="">Elegir selección</option>{selections.map((selection) => <option key={selection.id} value={selection.id}>{selection.description}</option>)}</select></section>}
                    {form.type !== 'multiple_option' && form.type !== 'selection' && <div className="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground">Tipo seleccionado: <strong className="text-foreground">{typeLabel}</strong>. La persona responderá mediante un campo de tipo {typeLabel?.toLowerCase()}.</div>}
                </div>
                <DialogFooter><Button type="button" variant="outline" onClick={() => setForm(newForm())} disabled={busy}>Limpiar</Button><Button type="button" onClick={submit} disabled={busy}>{busy ? 'Guardando...' : questionToEdit ? 'Actualizar pregunta' : 'Guardar pregunta'}</Button></DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
