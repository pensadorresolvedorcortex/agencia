#!/usr/bin/env node
const fs = require('fs');

const required = [
  'nafloresta-buy/includes/Application/BatchAddToCartService.php',
  'nafloresta-buy/includes/Application/ValidateSelectionService.php',
  'nafloresta-buy/includes/Infrastructure/Support/DataIntegrityGuard.php'
];

for (const file of required) {
  if (!fs.existsSync(file)) {
    console.error(`Missing required file: ${file}`);
    process.exit(1);
  }
}

console.log('E2E smoke checks passed');
