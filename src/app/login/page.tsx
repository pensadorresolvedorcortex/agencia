"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function LoginPage() {
  const router = useRouter();
  const [error, setError] = useState("");
  async function submit(formData: FormData) {
    setError("");
    const res = await fetch("/api/auth/login", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ email: formData.get("email"), password: formData.get("password") }) });
    if (!res.ok) return setError("E-mail ou senha inválidos.");
    router.push("/dashboard");
  }
  return <main className="flex min-h-screen items-center justify-center p-6"><form action={submit} className="w-full max-w-md space-y-4 rounded-xl bg-white p-8 shadow"><h1 className="text-2xl font-bold">Acesso ao sistema</h1><input className="w-full rounded border p-3" name="email" type="email" placeholder="E-mail" required /><input className="w-full rounded border p-3" name="password" type="password" placeholder="Senha" required />{error && <p className="text-sm text-red-700">{error}</p>}<button className="w-full rounded bg-blue-800 p-3 font-semibold text-white">Entrar</button></form></main>;
}
