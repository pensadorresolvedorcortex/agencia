import { NextResponse } from "next/server";
import bcrypt from "bcryptjs";
import { UserRole } from "@prisma/client";
import { z } from "zod";
import { getSession, assertRole } from "@/lib/auth";
import { prisma } from "@/lib/prisma";
import { auditLog } from "@/lib/audit";

const createUserSchema = z.object({
  name: z.string().min(3),
  email: z.string().email().transform((v) => v.toLowerCase()),
  password: z.string().min(10),
  role: z.nativeEnum(UserRole),
});

export async function GET() {
  const session = await getSession();
  assertRole(session, [UserRole.ADMIN]);
  const users = await prisma.user.findMany({ orderBy: { createdAt: "desc" }, select: { id: true, name: true, email: true, role: true, status: true, createdAt: true } });
  return NextResponse.json(users);
}

export async function POST(request: Request) {
  const session = await getSession();
  assertRole(session, [UserRole.ADMIN]);
  const parsed = createUserSchema.parse(await request.json());
  const user = await prisma.user.create({ data: { name: parsed.name, email: parsed.email, passwordHash: await bcrypt.hash(parsed.password, 12), role: parsed.role, createdById: session!.id }, select: { id: true, name: true, email: true, role: true, status: true } });
  await auditLog({ actorId: session!.id, action: "USER_CREATED", entity: "User", entityId: user.id, metadata: { role: user.role, email: user.email } });
  return NextResponse.json(user, { status: 201 });
}
