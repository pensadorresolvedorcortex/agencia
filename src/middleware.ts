import { NextRequest, NextResponse } from "next/server";

export function middleware(request: NextRequest) {
  const hasSession = Boolean(request.cookies.get("agencia_session")?.value);
  const isProtected = request.nextUrl.pathname.startsWith("/dashboard");
  if (isProtected && !hasSession) return NextResponse.redirect(new URL("/login", request.url));
  if (request.nextUrl.pathname === "/login" && hasSession) return NextResponse.redirect(new URL("/dashboard", request.url));
  return NextResponse.next();
}

export const config = { matcher: ["/dashboard/:path*", "/login"] };
