const express = require("express");
const { Pool } = require("pg");
const cors = require("cors");

const app = express();
app.use(cors());
app.use(express.json());

// 🟡 Pool setup using environment variable from Render
const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: {
    rejectUnauthorized: false,
  },
});

// 1️⃣ Route to add a new message
app.post("/messages", async (req, res) => {
  const { content } = req.body;

  try {
    const result = await pool.query(
      "INSERT INTO messages (content) VALUES ($1) RETURNING *",
      [content]
    );
    res.status(201).json(result.rows[0]);
  } catch (err) {
    console.error("❌ Insert Error:", err);
    res.status(500).json({ error: "Database error" });
  }
});

// 2️⃣ Route to fetch all messages
app.get("/messages", async (req, res) => {
  try {
    const result = await pool.query(
      "SELECT * FROM messages ORDER BY created_at DESC"
    );
    res.json(result.rows);
  } catch (err) {
    console.error("❌ Fetch Error:", err);
    res.status(500).json({ error: "Database error" });
  }
});

// 🟢 Start the server
const PORT = process.env.PORT || 3001;
app.listen(PORT, () => {
  console.log(`✅ Server is running on port ${PORT}`);
});
