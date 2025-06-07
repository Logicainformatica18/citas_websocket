// resources/js/pages/products/modal.tsx

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
import { Loader2 } from 'lucide-react';

export default function ProductModal({
  open,
  onClose,
  onSaved,
  productToEdit,
}: {
  open: boolean;
  onClose: () => void;
  onSaved: (product: any) => void;
  productToEdit?: any;
}) {
  const [formData, setFormData] = useState({
    code: '',
    sku: '',
    description: '',
    detail: '',
    brand: '',
    model: '',
    serial_number: '',
    condition: '',
    state: '',
    quantity: 1,
    price: 0,
    location: '',
  });

  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);

  useEffect(() => {
    if (productToEdit) {
      setFormData({
        code: productToEdit.code || '',
        sku: productToEdit.sku || '',
        description: productToEdit.description || '',
        detail: productToEdit.detail || '',
        brand: productToEdit.brand || '',
        model: productToEdit.model || '',
        serial_number: productToEdit.serial_number || '',
        condition: productToEdit.condition || '',
        state: productToEdit.state || '',
        quantity: productToEdit.quantity || 1,
        price: productToEdit.price || 0,
        location: productToEdit.location || '',
      });
      if (productToEdit.file_1) {
        setPreview(`../../uploads/${productToEdit.file_1}`);
      }
    } else {
      handleReset();
    }
  }, [productToEdit]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: ['quantity', 'price'].includes(name) ? Number(value) : value,
    }));
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;
    if (file) {
      setFile(file);
      setPreview(file.type.startsWith('image') ? URL.createObjectURL(file) : file.name);
    }
  };

  const handleSubmit = async () => {
    try {
      setUploading(true);
      setProgress(0);

      const data = new FormData();
      Object.entries(formData).forEach(([key, value]) => data.append(key, String(value)));
      if (file) data.append('file_1', file);

      const url = productToEdit ? `/products/${productToEdit.id}` : '/products';
      if (productToEdit) data.append('_method', 'PUT');

      const response = await axios.post(url, data, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          if (e.total) setProgress(Math.round((e.loaded * 100) / e.total));
        },
      });

      toast.success(productToEdit ? 'Producto actualizado ✅' : 'Producto guardado ✅');
      onSaved(response.data.product);
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
      code: '',
      sku: '',
      description: '',
      detail: '',
      brand: '',
      model: '',
      serial_number: '',
      condition: '',
      state: '',
      quantity: 1,
      price: 0,
      location: '',
    });
    setFile(null);
    setPreview(null);
  };

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{productToEdit ? 'Editar Producto' : 'Nuevo Producto'}</DialogTitle>
        </DialogHeader>

        {uploading && (
          <div className="w-full mb-4">
            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
              <div className="h-2 bg-blue-500 transition-all duration-100" style={{ width: `${progress}%` }} />
            </div>
            <p className="text-xs text-center text-gray-500 mt-1">{progress}%</p>
          </div>
        )}

        <div className="grid gap-4 py-4">
          {Object.entries(formData).map(([key, value]) => (
            <div key={key} className="grid grid-cols-4 items-center gap-4">
             <Label htmlFor={key} className="text-right">
  {{
    code: 'Código',
    sku: 'SKU',
    description: 'Descripción',
    detail: 'Detalle',
    brand: 'Marca',
    model: 'Modelo',
    serial_number: 'Número de serie',
    condition: 'Condición',
    state: 'Estado',
    quantity: 'Cantidad',
    price: 'Precio',
    location: 'Ubicación'
  }[key as keyof typeof formData] || key}
</Label>

              {key === 'detail' ? (
                <textarea
                  name={key}
                  id={key}
                  rows={3}
                  value={value}
                  onChange={handleChange}
                  className="col-span-3 border rounded px-3 py-2 text-sm"
                />
              ) : key === 'state' ? (
                <select
                  name={key}
                  id={key}
                  value={value}
                  onChange={handleChange}
                  className="col-span-3 border rounded px-3 py-2 text-sm"
                >
                  <option value="">Selecciona una opción</option>
                  <option value="Disponible">Disponible</option>
                  <option value="Asignado">Asignado</option>
                  <option value="Dañado">Dañado</option>
                </select>
              ) : (
                <Input
                  type={['quantity', 'price'].includes(key) ? 'number' : 'text'}
                  name={key}
                  id={key}
                  value={value}
                  onChange={handleChange}
                  min={['quantity', 'price'].includes(key) ? 0 : undefined}
                  className="col-span-3"
                />
              )}
            </div>
          ))}

          {/* Archivo */}
          <div className="grid grid-cols-4 items-center gap-4">
            <Label htmlFor="file_1" className="text-right">Archivo</Label>
            <div className="col-span-3">
              <Input type="file" name="file_1" id="file_1" onChange={handleFileChange} />
              {preview && (
                <div className="mt-2">
                  {preview.startsWith('blob:') ? (
                    <img src={preview} alt="preview" className="w-16 h-16 object-cover rounded" />
                  ) : (
                    <a href={preview} className="text-blue-600 underline text-sm">{preview}</a>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>

        <DialogFooter className="flex justify-between">
          <div className="flex gap-2">
            <Button variant="outline" onClick={handleReset} disabled={uploading}>Nuevo</Button>
            <Button onClick={handleSubmit} disabled={uploading}>
              {uploading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {productToEdit ? 'Actualizar' : 'Guardar'}
            </Button>
          </div>
          <Button variant="ghost" onClick={onClose} disabled={uploading}>Cerrar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
