import { openDb } from '../db/db.js';const db=await openDb();
await db.run("INSERT OR IGNORE INTO species (name) VALUES ('Bovino'), ('Porcino'), ('Ovino')");
const sp=await db.get("SELECT id FROM species WHERE name = 'Bovino'");
await db.run("INSERT OR IGNORE INTO breeds (species_id, name) VALUES (?, 'Brahman'), (?, 'Holstein')", sp.id, sp.id);
await db.run("INSERT OR IGNORE INTO farms (name, location) VALUES ('Finca La Esperanza', 'Antioquia'), ('Finca El Roble', 'Cundinamarca')");
console.log('Seeds base listos 🌱');process.exit(0);