-- Test data. Run on a fresh database:
--   rm -f comp307.db && sqlite3 comp307.db < schema.sql && sqlite3 comp307.db < seed.sql
--
-- Tickets (no login needed):  t1 = Brady (leader of group 1)
--                             t2 = Isha  (member of group 1)
--                             t3 = Karan (member of group 2 only, use to test 403s)
-- UI login: brady@test.com / pass (stored as a password_hash() of "pass")

INSERT INTO users (id, email, display_name, pass_hash) VALUES
  (1, 'brady@test.com', 'Brady', '$2y$12$chD3lhVL4hL0JrvUSie3oeagmLFCDQb6zKlcmOvXnotIujCXVuAOi'),
  (2, 'isha@test.com',  'Isha',  '$2y$12$chD3lhVL4hL0JrvUSie3oeagmLFCDQb6zKlcmOvXnotIujCXVuAOi'),
  (3, 'karan@test.com', 'Karan', '$2y$12$chD3lhVL4hL0JrvUSie3oeagmLFCDQb6zKlcmOvXnotIujCXVuAOi');

INSERT INTO sessions (token, user_id, expires_at) VALUES
  ('t1', 1, datetime('now', '+1 year')),
  ('t2', 2, datetime('now', '+1 year')),
  ('t3', 3, datetime('now', '+1 year'));

INSERT INTO study_groups (id, name, description, invite_code, created_by) VALUES
  (1, 'COMP307', 'Web dev study group', 'ABC123', 1),
  (2, 'MATH240', 'Discrete math',       'XYZ789', 3);

INSERT INTO group_members (group_id, user_id, role) VALUES
  (1, 1, 'leader'),
  (1, 2, 'member'),
  (2, 3, 'leader');

INSERT INTO notes (id, group_id, author_id, title, content, version) VALUES
  (1, 1, 1, 'HTTP basics',
'HTTP (HyperText Transfer Protocol) is a stateless request/response protocol.
A request has a method, a path, headers and an optional body.
GET retrieves a resource and should have no side effects. POST submits data and may change server state.
PUT replaces a resource, DELETE removes it. GET, PUT and DELETE are idempotent; POST is not.
Status codes: 2xx success (200 OK, 201 Created), 3xx redirection (301 Moved Permanently, 302 Found),
4xx client error (400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 409 Conflict),
5xx server error (500 Internal Server Error, 502 Bad Gateway).
Because HTTP is stateless, servers use cookies or tokens to remember who a user is between requests.', 1),

  (2, 1, 2, 'SQL and databases',
'A primary key uniquely identifies each row in a table. A foreign key references a primary key in another table.
ON DELETE CASCADE deletes child rows automatically when the parent row is deleted.
JOIN combines rows from two tables. INNER JOIN keeps only matching rows; LEFT JOIN keeps every row from the left table
and fills missing right-side columns with NULL.
GROUP BY collapses rows into groups so aggregate functions like COUNT, SUM and MAX can be applied per group.
Prepared statements send the SQL and the values separately, which prevents SQL injection.
A transaction groups statements so they all succeed or all fail (atomicity).', 1),

  (3, 2, 3, 'Proofs',
'A proof by induction shows a base case holds, then shows that if the statement holds for n it holds for n+1.
A proof by contradiction assumes the statement is false and derives something impossible.', 1);

INSERT INTO note_revisions (note_id, version, content)
  SELECT id, version, content FROM notes;

-- One hand-made quiz in group 1 for testing startquiz/submitquiz before create-quiz works.
INSERT INTO quizzes (id, group_id, created_by, title, question_count, content) VALUES
  (1, 1, 1, 'HTTP warm-up', 3, '{
  "sources": [{"note_id": 1, "version": 1}],
  "questions": [
    {"id": "q1", "type": "mcq", "prompt": "What does HTTP stand for?",
     "options": [{"id": "a", "text": "HyperText Transfer Protocol"},
                 {"id": "b", "text": "High Transfer Text Process"},
                 {"id": "c", "text": "Host Transfer Text Protocol"}],
     "answer": "a", "explanation": "HTTP = HyperText Transfer Protocol."},
    {"id": "q2", "type": "mcq", "prompt": "Which status code means the user is logged in but not allowed?",
     "options": [{"id": "a", "text": "401"}, {"id": "b", "text": "403"}, {"id": "c", "text": "404"}],
     "answer": "b", "explanation": "403 Forbidden: authenticated but not permitted. 401 means not logged in."},
    {"id": "q3", "type": "mcq", "prompt": "Which method is NOT idempotent?",
     "options": [{"id": "a", "text": "GET"}, {"id": "b", "text": "PUT"}, {"id": "c", "text": "POST"}],
     "answer": "c", "explanation": "Repeating a POST can create duplicate resources."}
  ]
}');

-- One finished attempt so the leaderboard isn't all zeros (Isha scored 2/3).
INSERT INTO quiz_attempts (quiz_id, user_id, answers, score, max_score, submitted_at) VALUES
  (1, 2, '{"q1":"a","q2":"a","q3":"c"}', 2, 3, CURRENT_TIMESTAMP);
