import { useEffect, useState, useRef } from 'react';
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
import ProductSearch from '../products/search';
import ArticleListTable from './modaltable';

export default function ArticleModal({
  open,
  onClose,
  onSaved,
  articleToEdit,
  transfer_id,
}: {
  open: boolean;
  onClose: () => void;
  onSaved: (article: any) => void;
  articleToEdit?: any;
  transfer_id?: number;
}) {
  const [formData, setFormData] = useState({
    title: '',
    description: '',
    details: '',
    quanty: 1,
    price: 0,
    code: '',
    condition: '',
    state: '',
    product_id: null,
    file_1: null as File | null,
    file_2: null as File | null,
    file_3: null as File | null,
    file_4: null as File | null,
  });

  const [productSearchQuery, setProductSearchQuery] = useState('');
  const [articles, setArticles] = useState<typeof formData[]>([]);
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);

  const fileInputsRef = useRef<{ [key: string]: HTMLInputElement | null }>({});

  useEffect(() => {
    if (articleToEdit) {
        setArticles([{ ...articleToEdit }]);
        setFormData({
          title: articleToEdit.title || '',
          description: articleToEdit.description || '',
          details: articleToEdit.details || '',
          quanty: articleToEdit.quanty || 1,
          price: articleToEdit.price || 0,
          code: articleToEdit.code || '',
          condition: articleToEdit.condition || '',
          state: articleToEdit.state || '',
          product_id: articleToEdit.product_id || null,
          file_1: null, // Aquí ya no cargas archivo
          file_2: null,
          file_3: null,
          file_4: null,

        });
        setProductSearchQuery(articleToEdit.description || ''); // 👈 Aquí agregas esto
      } else {
        setArticles([]);
        setFormData({
          title: '',
          description: '',
          details: '',
          quanty: 1,
          price: 0,
          code: '',
          condition: '',
          state: '',
          product_id: null,
          file_1: null,
          file_2: null,
          file_3: null,
          file_4: null,
        });
      }

  }, [articleToEdit]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: name === 'quanty' || name === 'price' ? Number(value) : value,
    }));
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const name = e.target.name;
    const file = e.target.files?.[0] ?? null;
    if (file) {
      setFormData((prev) => ({
        ...prev,
        [name]: file,
      }));
    }
  };

  const handleAddArticle = () => {
    if (formData.description && formData.quanty > 0 && formData.product_id) {
      setArticles((prev) => [...prev, formData]);
      setFormData({
        title: '',
        description: '',
        details: '',
        quanty: 1,
        price: 0,
        code: '',
        condition: '',
        state: '',
        product_id: null,
        file_1: null,
        file_2: null,
        file_3: null,
        file_4: null,
      });
      setProductSearchQuery('');

      // ✅ Limpiar los inputs file visualmente
      Object.keys(fileInputsRef.current).forEach((key) => {
        if (fileInputsRef.current[key]) {
          fileInputsRef.current[key]!.value = '';
        }
      });
    } else {
      toast.error('Completa los campos obligatorios.');
    }
  };

  const handleDeleteArticle = (index: number) => {
    setArticles((prev) => prev.filter((_, idx) => idx !== index));
  };

  const handleSubmit = async () => {
    if (articles.length === 0) {
      toast.error('Debes agregar al menos un artículo.');
      return;
    }
    try {
      setUploading(true);
      setProgress(0);
      const formDataToSend = new FormData();
      formDataToSend.append('transfer_id', String(transfer_id));
      articles.forEach((article, idx) => {
        Object.entries(article).forEach(([key, value]) => {
          if (value !== null) {
            if (value instanceof File) {
              formDataToSend.append(`articles[${idx}][${key}]`, value);
            } else {
              formDataToSend.append(`articles[${idx}][${key}]`, String(value));
            }
          }
        });
      });
      const response = await axios.post('/articles/bulk-store', formDataToSend, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          if (e.total) {
            setProgress(Math.round((e.loaded * 100) / e.total));
          }
        },
      });
      toast.success('Artículos guardados correctamente ✅');
      onSaved(response.data);
      setArticles([]);
      onClose();
    } catch (error) {
      console.error('❌ Error al guardar:', error);
      toast.error('Hubo un error al guardar los artículos');
    } finally {
      setUploading(false);
      setProgress(0);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(value) => !value && onClose()}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>{articleToEdit ? 'Editar Artículo' : 'Agregar Artículos'}</DialogTitle>
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
          <div className="grid grid-cols-4 items-center gap-4">
            <Label className="text-right">Buscar producto</Label>
            <div className="col-span-3">
              <ProductSearch
                query={productSearchQuery}
                setQuery={setProductSearchQuery}
                onSelect={(product) => {
                  setFormData((prev) => ({
                    ...prev,
                    title: product.description,
                    description: product.description,
                    code: product.code ?? '',
                    product_id: product.id,
                  }));
                  setProductSearchQuery(product.description);
                }}
              />
            </div>
          </div>

          {[{ name: 'details', label: 'Detalles' }, { name: 'quanty', label: 'Cantidad' }, { name: 'code', label: 'Código' }, { name: 'condition', label: 'Condición' }, { name: 'state', label: 'Estado' }].map(({ name, label }) => (
            <div key={name} className="grid grid-cols-4 items-center gap-4">
              <Label htmlFor={name} className="text-right">{label}</Label>
              {name === 'details' ? (
                <textarea
                  id={name}
                  name={name}
                  value={(formData as any)[name]}
                  onChange={handleChange}
                  className="col-span-3 border rounded px-3 py-2 text-sm"
                  rows={3}
                />
              ) : name === 'state' ? (
                <select
                  id={name}
                  name={name}
                  value={(formData as any)[name]}
                  onChange={handleChange}
                  className="col-span-3 border rounded px-3 py-2 text-sm"
                >
                  <option value="">Selecciona estado</option>
                  <option value="bueno">Bueno</option>
                  <option value="daño leve">Daño leve</option>
                  <option value="daño moderado">Daño moderado</option>
                </select>
              ) : (
                <Input
                  id={name}
                  name={name}
                  type={name === 'quanty' ? 'number' : 'text'}
                  min={name === 'quanty' ? 0 : undefined}
                  value={(formData as any)[name]}
                  onChange={handleChange}
                  className="col-span-3"
                />
              )}
            </div>
          ))}

          {[1, 2, 3, 4].map((num) => {
            const field = `file_${num}`;
            const file = formData[field as keyof typeof formData] as File | null;
            return (
              <div key={field} className="grid grid-cols-4 items-start gap-4">
                <Label htmlFor={field} className="text-right mt-2">Archivo {num}</Label>
                <div className="col-span-3">
                  <Input type="file" id={field} name={field}
                    ref={(el) => (fileInputsRef.current[field] = el)}
                    onChange={handleFileChange} />
                  {file && (
                    <div className="mt-2 text-sm text-gray-700">
                      {file.type.startsWith('image') ? (
                        <img
                          src={URL.createObjectURL(file)}
                          alt="Preview"
                          className="w-16 h-16 object-cover rounded"
                        />
                      ) : (
                        <span>{file.name}</span>
                      )}
                    </div>
                  )}
                </div>
              </div>
            );
          })}

          <div className="flex justify-end">
            <Button variant="outline" onClick={handleAddArticle}>Agregar a lista</Button>
          </div>

          <ArticleListTable
            articles={articles}
            onDelete={handleDeleteArticle}
            onSubmit={handleSubmit}
            uploading={uploading}
          />
        </div>

        <DialogFooter>
          <Button variant="ghost" onClick={onClose} disabled={uploading}>Cerrar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
