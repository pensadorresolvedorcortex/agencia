import Link from "next/link";
import { redirect } from "next/navigation";
import { UserRole } from "@prisma/client";
import { getSession } from "@/lib/auth";

export default async function DashboardPage() {
  const session = await getSession();
  if (!session) redirect("/login");
  return <main className="mx-auto max-w-6xl p-8"><div className="rounded-xl bg-white p-8 shadow"><p className="text-sm uppercase tracking-wide text-blue-800">Administração pública municipal</p><h1 className="mt-2 text-3xl font-bold">Ordens de Fornecimento e Serviço</h1><p className="mt-4 text-slate-600">Base inicial com autenticação, perfis de acesso, usuários e auditoria. Os módulos contratuais serão adicionados por etapas para preservar controle de saldo e rastreabilidade.</p><div className="mt-8 grid gap-4 md:grid-cols-3"><div className="rounded border p-4"><strong>Usuário</strong><p>{session.name}</p><p className="text-sm text-slate-500">{session.role}</p></div><div className="rounded border p-4"><strong>Próxima etapa</strong><p>Contratos, lotes e itens</p></div>{session.role === UserRole.ADMIN && <Link className="rounded border border-blue-800 p-4 text-blue-900 hover:bg-blue-50" href="/dashboard/users"><strong>Cadastro de usuários</strong><p>Gerenciar acesso por perfil</p></Link>}</div></div></main>;
}
