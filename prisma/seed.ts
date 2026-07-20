import { PrismaClient, UserRole } from "@prisma/client";
import bcrypt from "bcryptjs";

const prisma = new PrismaClient();

async function main() {
  const email = process.env.ADMIN_EMAIL ?? "admin@municipio.local";
  const password = process.env.ADMIN_PASSWORD ?? "Admin@12345";
  const passwordHash = await bcrypt.hash(password, 12);

  await prisma.user.upsert({
    where: { email },
    update: { passwordHash, role: UserRole.ADMIN, status: "ACTIVE" },
    create: { name: "Administrador do Sistema", email, passwordHash, role: UserRole.ADMIN },
  });
}

main()
  .finally(async () => prisma.$disconnect());
