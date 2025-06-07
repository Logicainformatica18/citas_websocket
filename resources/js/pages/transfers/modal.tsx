import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogFooter,
    DialogTitle
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react'; // 👈 Spinner icon
import { notifyTransfer } from './notify';

export default function TransferModal({
    open,
    onClose,
    onSaved,
    transferToEdit,
}: {
    open: boolean;
    onClose: () => void;
    onSaved: (transfer: any) => void;
    transferToEdit?: any;
}) {
    const [formData, setFormData] = useState({
        description: '',
        details: '',
        sender_firstname: '',
        sender_lastname: '',
        sender_email: '',
        receiver_firstname: '',
        receiver_lastname: '',
        receiver_email: '',
    });

    const [file, setFile] = useState<File | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [uploading, setUploading] = useState(false);
    const [progress, setProgress] = useState(0);
    const [notifying, setNotifying] = useState(false);
    const handleNotify = async () => {
        if (!transferToEdit) return;
        setNotifying(true);
        try {
          await notifyTransfer(transferToEdit.id);
        } catch (error) {
          // Error ya lo maneja notifyTransfer
        } finally {
          setNotifying(false);
        }
      };

    useEffect(() => {
        if (transferToEdit) {
            setFormData({
                description: transferToEdit.description || '',
                details: transferToEdit.details || '',
                sender_firstname: transferToEdit.sender_firstname || '',
                sender_lastname: transferToEdit.sender_lastname || '',
                sender_email: transferToEdit.sender_email || '',
                receiver_firstname: transferToEdit.receiver_firstname || '',
                receiver_lastname: transferToEdit.receiver_lastname || '',
                receiver_email: transferToEdit.receiver_email || '',
            });
            setPreview(transferToEdit.file_1 ? `../../uploads/${transferToEdit.file_1}` : null);
        } else {
            handleReset();
        }
    }, [transferToEdit]);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        const { name, value } = e.target;
        setFormData({ ...formData, [name]: value });
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const newFile = e.target.files?.[0] ?? null;
        setFile(newFile);
        if (newFile) {
            setPreview(newFile.type.startsWith('image') ? URL.createObjectURL(newFile) : newFile.name);
        }
    };

    const handleSubmit = async () => {
        try {
            setUploading(true);
            setProgress(0);

            const data = new FormData();
            Object.entries(formData).forEach(([key, value]) => {
                data.append(key, value);
            });
            if (file) data.append('file_1', file);

            const url = transferToEdit ? `/transfers/${transferToEdit.id}` : '/transfers';
            if (transferToEdit) data.append('_method', 'PUT');

            const response = await axios.post(url, data, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress: (event) => {
                    if (event.total) {
                        const percent = Math.round((event.loaded * 100) / event.total);
                        setProgress(percent);
                    }
                },
            });

            toast.success(transferToEdit ? 'Transfer actualizado ✅' : 'Transfer guardado ✅');
            onSaved(response.data.transfer);
            handleReset();
            onClose();
        } catch (error) {
            console.error('❌ Error al guardar:', error);
            toast.error('Hubo un error al guardar');
        } finally {
            setUploading(false);
            setProgress(0);
        }
    };

    const handleReset = () => {
        setFormData({
            description: '',
            details: '',
            sender_firstname: '',
            sender_lastname: '',
            sender_email: '',
            receiver_firstname: '',
            receiver_lastname: '',
            receiver_email: '',
        });
        setFile(null);
        setPreview(null);
    };

    return (
        <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{transferToEdit ? 'Editar Transfer' : 'Nuevo Transfer'}</DialogTitle>
                </DialogHeader>

                {uploading && (
                    <div className="w-full mb-4">
                        <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div
                                className="h-2 bg-blue-500 transition-all duration-100"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <p className="text-xs text-center text-gray-500 mt-1">{progress}%</p>
                    </div>
                )}

                <div className="grid gap-4 py-4">
                    {[
                        'description',
                        'details',
                        'sender_firstname',
                        'sender_lastname',
                        'sender_email',
                        'receiver_firstname',
                        'receiver_lastname',
                        'receiver_email'
                    ].map((field) => (
                        <div key={field} className="grid grid-cols-4 items-center gap-4">
                         <Label htmlFor={field} className="text-right">
  {{
    description: 'Descripción',
    details: 'Detalles',
    sender_firstname: 'Nombre del remitente',
    sender_lastname: 'Apellido del remitente',
    sender_email: 'Correo del remitente',
    receiver_firstname: 'Nombre del receptor',
    receiver_lastname: 'Apellido del receptor',
    receiver_email: 'Correo del receptor',
    file_1: 'Archivo'
  }[field] || field}
</Label>

                            {field === 'details' ? (
                                <textarea
                                    id={field}
                                    name={field}
                                    value={(formData as any)[field]}
                                    onChange={handleChange}
                                    className="col-span-3 border rounded px-3 py-2 text-sm"
                                    rows={3}
                                />
                            ) : (
                                <Input
                                    type={field.includes('email') ? 'email' : 'text'}
                                    id={field}
                                    name={field}
                                    value={(formData as any)[field]}
                                    onChange={handleChange}
                                    className="col-span-3"
                                />
                            )}
                        </div>
                    ))}

                    <div className="grid grid-cols-4 items-start gap-4">
                        <Label htmlFor="file_1" className="text-right mt-2">Archivo</Label>
                        <div className="col-span-3">
                            <div className="flex items-center gap-4">
                                <Input type="file" name="file_1" id="file_1" onChange={handleFileChange} />
                                {preview && (
                                    preview.startsWith('blob:') ? (
                                        <img src={preview} alt="preview" className="w-12 h-12 object-cover rounded" />
                                    ) : (
                                        <a
                                            href={preview}
                                            download
                                            className="text-sm text-blue-600 underline truncate max-w-[120px]"
                                        >
                                            {preview}
                                        </a>
                                    )
                                )}
                            </div>
                            {file && (
                                <div className={`text-xs mt-1 ${file.size / 1024 > 2000 ? 'text-red-600' : 'text-blue-600'}`}>
                                    Tamaño: {(file.size / 1024).toFixed(1)} KB
                                </div>
                            )}
                        </div>
                    </div>

                    <span className="text-xs text-right block text-gray-600">
                        El archivo debe ser menor a 2000 KB
                    </span>
                </div>

                <DialogFooter className="flex justify-between">
                    <div className="flex gap-2">
                    <Button
                            variant="secondary"
                            disabled={!transferToEdit || uploading || notifying}
                            onClick={handleNotify}
                        >
                            {notifying && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Notificar
                        </Button>

                        <Button variant="outline" onClick={handleReset} disabled={uploading}>
                            Nuevo
                        </Button>
                        <Button onClick={handleSubmit} disabled={uploading}>
                            {uploading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            {transferToEdit ? 'Actualizar' : 'Guardar'}
                        </Button>
                    </div>
                    <Button variant="ghost" onClick={onClose} disabled={uploading}>
                        Cerrar
                    </Button>



                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
