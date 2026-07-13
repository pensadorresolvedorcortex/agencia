import { redirect } from "next/navigation";
import { UserRole } from "@prisma/client";
import bcrypt from "bcryptjs";
import { getSession } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { auditLog } from "@/lib/audit";

async function createUser(formData: FormData) {
  "use server";
  const session = await getSession();
  if (!session || session.role !== UserRole.ADMIN) throw new Error("Acesso negado");
  const email = String(formData.get("email")).toLowerCase();
  const user = await prisma.user.create({ data: { name: String(formData.get("name")), email, role: formData.get("role") as UserRole, passwordHash: await bcrypt.hash(String(formData.get("password")), 12), createdById: session.id } });
  await auditLog({ actorId: session.id, action: "USER_CREATED", entity: "User", entityId: user.id, metadata: { email, role: user.role } });
}

export default async function UsersPage() {
  const session = await getSession();
  if (!session) redirect("/login");
  if (session.role !== UserRole.ADMIN) redirect("/dashboard");
  const users = await prisma.user.findMany({ orderBy: { createdAt: "desc" } });
  return <main className="mx-auto max-w-6xl p-8"><h1 className="text-3xl font-bold">Usuários</h1><form action={createUser} className="mt-6 grid gap-3 rounded-xl bg-white p-6 shadow md:grid-cols-5"><input className="rounded border p-3" name="name" placeholder="Nome" required /><input className="rounded border p-3" name="email" type="email" placeholder="E-mail" required /><input className="rounded border p-3" name="password" type="password" placeholder="Senha inicial" minLength={10} required /><select className="rounded border p-3" name="role" defaultValue="CONSULTA">{Object.values(UserRole).map((role) => <option key={role}>{role}</option>)}</select><button className="rounded bg-blue-800 p-3 font-semibold text-white">Cadastrar</button></form><table className="mt-6 w-full overflow-hidden rounded-xl bg-white shadow"><thead className="bg-slate-100 text-left"><tr><th className="p-3">Nome</th><th>E-mail</th><th>Perfil</th><th>Status</th></tr></thead><tbody>{users.map((user) => <tr className="border-t" key={user.id}><td className="p-3">{user.name}</td><td>{user.email}</td><td>{user.role}</td><td>{user.status}</td></tr>)}</tbody></table></main>;
}
