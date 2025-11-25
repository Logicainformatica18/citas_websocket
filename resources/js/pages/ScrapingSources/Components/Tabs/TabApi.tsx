import React from "react";

interface Props {
    form: any;
    setForm: React.Dispatch<React.SetStateAction<any>>;
}

export default function TabApi({ form, setForm }: Props) {
    return (
        <>
            <div>
                <label className="text-sm font-medium">API URL</label>
                <input
                    className="mt-2 w-full px-3 py-2 rounded-md border dark:bg-gray-800"
                    value={form.api_url}
                    onChange={(e) =>
                        setForm({ ...form, api_url: e.target.value })
                    }
                />
            </div>

            <div className="mt-4">
                <label className="text-sm font-medium">API Key (opcional)</label>
                <input
                    type="password"
                    className="mt-2 w-full px-3 py-2 rounded-md border dark:bg-gray-800"
                    value={form.api_key}
                    onChange={(e) =>
                        setForm({ ...form, api_key: e.target.value })
                    }
                />
            </div>
        </>
    );
}
