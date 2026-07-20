import { NextResponse } from "next/server";
import { clearSessionCookie, getSession } from "@/lib/auth";
import { auditLog } from "@/lib/audit";

export async function POST() {
  const session = await getSession();
  clearSessionCookie();
  if (session) await auditLog({ actorId: session.id, action: "LOGOUT", entity: "User", entityId: session.id });
  return NextResponse.json({ ok: true });
}
