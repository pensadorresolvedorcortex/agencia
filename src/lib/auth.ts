import { cookies } from "next/headers";
import { SignJWT, jwtVerify } from "jose";
import { UserRole } from "@prisma/client";

const SESSION_COOKIE = "agencia_session";
const secret = new TextEncoder().encode(process.env.JWT_SECRET ?? "dev-secret-change-me-32-characters");

export type SessionUser = { id: string; name: string; email: string; role: UserRole };

export async function createSessionToken(user: SessionUser) {
  return new SignJWT(user)
    .setProtectedHeader({ alg: "HS256" })
    .setIssuedAt()
    .setExpirationTime("8h")
    .sign(secret);
}

export async function getSession(): Promise<SessionUser | null> {
  const token = cookies().get(SESSION_COOKIE)?.value;
  if (!token) return null;
  try {
    const { payload } = await jwtVerify(token, secret);
    return payload as SessionUser;
  } catch {
    return null;
  }
}

export function setSessionCookie(token: string) {
  cookies().set(SESSION_COOKIE, token, { httpOnly: true, sameSite: "lax", secure: process.env.NODE_ENV === "production", path: "/", maxAge: 60 * 60 * 8 });
}

export function clearSessionCookie() { cookies().delete(SESSION_COOKIE); }

export function assertRole(user: SessionUser | null, roles: UserRole[]) {
  if (!user || !roles.includes(user.role)) throw new Error("FORBIDDEN");
}
