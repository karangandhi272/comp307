-- SQLite schema. Create the database file with:
--   sqlite3 comp307.db < schema.sql
-- Note: SQLite only enforces foreign keys when each connection runs
--   PRAGMA foreign_keys = ON;

CREATE TABLE users (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  email        VARCHAR(255) NOT NULL UNIQUE,
  display_name VARCHAR(100) NOT NULL,
  pass_hash    VARCHAR(255) NOT NULL,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sessions (
  token      VARCHAR(64) PRIMARY KEY,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  expires_at TIMESTAMP NOT NULL
);

CREATE TABLE study_groups (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        VARCHAR(100) NOT NULL,
  description TEXT,
  invite_code VARCHAR(20) NOT NULL UNIQUE,
  created_by  INTEGER REFERENCES users(id),
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  quiz_cooldown_until TIMESTAMP -- create-quiz.php: no new quiz generation for this group until then
);

CREATE TABLE group_members (
  group_id  INTEGER NOT NULL REFERENCES study_groups(id) ON DELETE CASCADE,
  user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role      TEXT DEFAULT 'member' CHECK (role IN ('leader', 'member')),
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (group_id, user_id)
);

CREATE TABLE notes (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  group_id   INTEGER NOT NULL REFERENCES study_groups(id) ON DELETE CASCADE,
  author_id  INTEGER REFERENCES users(id),
  title      VARCHAR(200) NOT NULL,
  content    TEXT,
  version    INTEGER DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP -- set this in your UPDATE query
);

CREATE TABLE note_revisions (
  note_id    INTEGER NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
  version    INTEGER NOT NULL,
  content    TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (note_id, version)
);

CREATE TABLE quizzes (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  group_id       INTEGER NOT NULL REFERENCES study_groups(id) ON DELETE CASCADE,
  created_by     INTEGER REFERENCES users(id),
  title          VARCHAR(200) NOT NULL,
  question_count INTEGER NOT NULL,
  content        TEXT NOT NULL CHECK (json_valid(content)), -- JSON
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE quiz_attempts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  quiz_id      INTEGER NOT NULL REFERENCES quizzes(id) ON DELETE CASCADE,
  user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  answers      TEXT CHECK (answers IS NULL OR json_valid(answers)), -- JSON, null until submitted
  score        INTEGER,            -- null until submitted
  max_score    INTEGER NOT NULL,
  started_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  submitted_at TIMESTAMP           -- null = in progress
);
