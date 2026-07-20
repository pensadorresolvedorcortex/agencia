import "./globals.css";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Ordens Administrativas", description: "Controle de ordens e saldos contratuais" };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return <html lang="pt-BR"><body>{children}</body></html>;
}
