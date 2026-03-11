import { useEffect, useState } from "react"
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogFooter,
    DialogTitle
} from "@/components/ui/dialog"

import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

import axios from "axios"
import { toast } from "sonner"

import {
    Loader2,
    Mail,
    User,
    IdCard,
    Phone,
    Lock,
    Eye,
    EyeOff
} from "lucide-react"

export default function UserModal({
    open,
    onClose,
    onSaved,
    userToEdit
}: {
    open: boolean
    onClose: () => void
    onSaved: (user: any) => void
    userToEdit?: any
}) {

    // mostrar contraseña por defecto
    const [showPassword, setShowPassword] = useState(true)

    const [formData, setFormData] = useState({
        firstname: "",
        lastname: "",
        email: "",
        dni: "",
        cellphone: "",
        sex: "",
        password: "isil2026" // contraseña por defecto
    })

    const [uploading, setUploading] = useState(false)

    useEffect(() => {

        if (userToEdit) {

            setFormData({
                firstname: userToEdit.firstname || "",
                lastname: userToEdit.lastname || "",
                email: userToEdit.email || "",
                dni: userToEdit.dni || "",
                cellphone: userToEdit.cellphone || "",
                sex: userToEdit.sex || "",
                password: "" // al editar queda vacío
            })

        } else {

            handleReset()

        }

    }, [userToEdit])


    const handleChange = (e: any) => {

        const { name, value } = e.target

        setFormData({
            ...formData,
            [name]: value
        })

    }


    const handleSubmit = async () => {

        try {

            setUploading(true)

            const data = new FormData()

            Object.entries(formData).forEach(([k, v]) => {
                data.append(k, v as string)
            })

            const url = userToEdit ? `/users/${userToEdit.id}` : "/users"

            if (userToEdit) {
                data.append("_method", "PUT")
            }

            const res = await axios.post(url, data)

            toast.success(userToEdit ? "Usuario actualizado" : "Usuario creado")

            onSaved(res.data.user)

            handleReset()
            onClose()

        } catch (err) {

            toast.error("Error al guardar")

        } finally {

            setUploading(false)

        }

    }


    const handleReset = () => {

        setFormData({
            firstname: "",
            lastname: "",
            email: "",
            dni: "",
            cellphone: "",
            sex: "",
            password: "isil2026" // reset vuelve a contraseña por defecto
        })

    }


    return (

        <Dialog open={open} onOpenChange={(v) => !v && onClose()}>

            <DialogContent className="sm:max-w-lg">

                <DialogHeader>
                    <DialogTitle>
                        {userToEdit ? "Editar usuario" : "Nuevo usuario"}
                    </DialogTitle>
                </DialogHeader>


                <div className="space-y-4 py-4">


                    {/* nombre */}

                    <div>

                        <Label className="flex items-center gap-2">
                            <User size={16} /> Nombre *
                        </Label>

                        <Input
                            name="firstname"
                            value={formData.firstname}
                            onChange={handleChange}
                        />

                    </div>


                    {/* apellido */}

                    <div>

                        <Label className="flex items-center gap-2">
                            <User size={16} /> Apellido *
                        </Label>

                        <Input
                            name="lastname"
                            value={formData.lastname}
                            onChange={handleChange}
                        />

                    </div>


                    {/* dni */}

                    {/* <div>

<Label className="flex items-center gap-2">
<IdCard size={16}/> DNI
</Label>

<Input
name="dni"
value={formData.dni}
onChange={handleChange}
/>

</div> */}


                    {/* email */}

                    <div>

                        <Label className="flex items-center gap-2">
                            <Mail size={16} /> Correo *
                        </Label>

                        <Input
                            type="email"
                            name="email"
                            value={formData.email}
                            onChange={handleChange}
                        />

                    </div>


                    {/* celular */}
                    {/*
<div>

<Label className="flex items-center gap-2">
<Phone size={16}/> Celular
</Label>

<Input
name="cellphone"
value={formData.cellphone}
onChange={handleChange}
/>

</div> */}


                    {/* sexo */}
                    {/*
<div>

<Label>Sexo</Label>

<select
name="sex"
value={formData.sex}
onChange={handleChange}
className="w-full border rounded-md px-3 py-2 text-sm"
>

<option value="">Seleccionar</option>
<option value="M">Masculino</option>
<option value="F">Femenino</option>

</select>

</div> */}


                    {/* contraseña */}

                    <div className="relative">

                        <Label className="flex items-center gap-2">
                            <Lock size={16} /> Contraseña *
                        </Label>

                        <Input
                            type={showPassword ? "text" : "password"}
                            name="password"
                            value={formData.password}
                            onChange={handleChange}
                        />

                        <button
                            type="button"
                            onClick={() => setShowPassword(!showPassword)}
                            className="absolute right-3 top-8 text-gray-500"
                        >

                            {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}

                        </button>

                    </div>


                </div>


                <DialogFooter className="flex justify-between">

                    <Button
                        variant="outline"
                        onClick={handleReset}
                        disabled={uploading}
                    >

                        Limpiar

                    </Button>


                    <Button
                        onClick={handleSubmit}
                        disabled={uploading}
                    >

                        {uploading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}

                        {userToEdit ? "Actualizar" : "Guardar"}

                    </Button>

                </DialogFooter>


            </DialogContent>

        </Dialog>

    )

}
