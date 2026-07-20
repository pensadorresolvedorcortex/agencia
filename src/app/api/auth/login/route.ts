import { NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { prisma } from "@/lib/prisma";
import { createSessionToken, setSessionCookie } from "@/lib/auth";
import { auditLog } from "@/lib/audit";

export async function POST(request: Request) {
  const { email, password } = await request.json();
  const user = await prisma.user.findUnique({ where: { email: String(email).toLowerCase() } });
  if (!user || user.status !== "ACTIVE" || !(await bcrypt.compare(String(password), user.passwordHash))) {
    await auditLog({ action: "LOGIN_FAILED", entity: "User", metadata: { email } });
    return NextResponse.json({ message: "Credenciais inválidas" }, { status: 401 });
  }
  const token = await createSessionToken({ id: user.id, name: user.name, email: user.email, role: user.role });
  setSessionCookie(token);
  await auditLog({ actorId: user.id, action: "LOGIN_SUCCESS", entity: "User", entityId: user.id });
  return NextResponse.json({ ok: true });
}
