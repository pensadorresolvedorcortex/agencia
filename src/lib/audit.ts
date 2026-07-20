import { prisma } from "@/lib/prisma";

export async function auditLog(input: { actorId?: string; action: string; entity: string; entityId?: string; metadata?: Record<string, unknown>; ipAddress?: string | null }) {
  await prisma.auditLog.create({ data: { ...input, metadata: input.metadata ?? undefined } });
}
