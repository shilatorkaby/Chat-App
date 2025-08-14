-- schema.sql  (idempotent; unified)
-- MySQL 8.0+
SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ======================
-- CONFIG (key/value)
-- ======================
CREATE TABLE IF NOT EXISTS config (
  id int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  setting VARCHAR(191) NOT NULL UNIQUE,
  value TEXT NOT NULL,
  comments TEXT NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

INSERT INTO config (setting, value)
VALUES ('base_url', 'http://localhost:8080/') ,
  (
    'brevo_api_key',
    'xkeysib-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
  ),
  (
    'email_from',
    'shilatprojects@gmail.com'
  ),
  ('email_name', 'My App'),
  ('otp_len', '6'),
  ('otp_valid_minutes', '10'),
  ('otp_cooldown_seconds', '30'),
  ('otp_limit_hour', '4'),
  ('otp_limit_day', '10'),
  ('token_ttl_hours', '24'),
  ('block_fails_per_hour', '10'),
  ('block_hours', '24') ON DUPLICATE KEY
UPDATE value =
VALUES(value);


-- ======================
-- USERS (auth canonical)
-- ======================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(191) NOT NULL,
  email VARCHAR(191) NOT NULL,
  display_name VARCHAR(191) DEFAULT NULL,
  profile_picture_url VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_username (username),
  UNIQUE KEY uniq_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ======================
-- AUTH: token sessions
-- ======================
CREATE TABLE IF NOT EXISTS auth_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(255) NOT NULL UNIQUE,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  INDEX (user_id),
  INDEX (expires_at),
  INDEX (revoked_at),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ======================
-- LOGIN telemetry / blocks
-- ======================
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (ip),
  INDEX (created_at),
  CONSTRAINT fk_login_attempts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS login_blocks (
  user_id INT NOT NULL,
  ip VARCHAR(45) NOT NULL,
  blocked_until DATETIME NOT NULL,
  PRIMARY KEY (user_id, ip),
  INDEX (blocked_until),
  CONSTRAINT fk_login_blocks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ======================
-- CONTACTS (owned address book)
-- ======================
CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT NOT NULL,
  -- who owns the chat list
  contact_user_id INT NULL,
  -- link to another user row (optional)
  contact_name VARCHAR(255) NOT NULL,
  -- display label (can differ from user)
  profile_picture_url VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- ensure one row per (owner, contact_user) pair
  UNIQUE KEY uniq_owner_contactuser (owner_user_id, contact_user_id),
  CONSTRAINT fk_contacts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_contacts_user FOREIGN KEY (contact_user_id) REFERENCES users(id) ON DELETE
  SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ======================
-- MESSAGES (per owner+contact)
-- ======================
CREATE TABLE IF NOT EXISTS messages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  owner_user_id INT NOT NULL,
  -- same idea as legacy belongs_to_username
  contact_id INT NOT NULL,
  -- FK to contacts.id
  msg_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_from_owner TINYINT(1) NOT NULL DEFAULT 1,
  msg_type ENUM('text', 'audio', 'image', 'file') DEFAULT 'text',
  msg_body LONGTEXT NULL,
  media_url VARCHAR(512) NULL,
  -- for audio/image/file paths
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_messages_owner_contact (owner_user_id, contact_id, msg_datetime),
  CONSTRAINT fk_messages_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ======================
-- OTP (one-time codes)
-- ======================
CREATE TABLE IF NOT EXISTS otp_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ip VARCHAR(45) NOT NULL,
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  INDEX (ip),
  INDEX (requested_at),
  CONSTRAINT fk_otpreq_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
CREATE TABLE IF NOT EXISTS otp_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  otp_hash CHAR(64) NOT NULL,
  -- sha256 hex
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  ip VARCHAR(45) NOT NULL,
  INDEX (user_id),
  INDEX (expires_at),
  INDEX (used_at),
  CONSTRAINT fk_otpcodes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
-- ======================
-- Seed (safe/idempotent)
-- ======================
-- baseline user for testing
INSERT INTO users (username, email, display_name, profile_picture_url)
SELECT 'testuser',
  'test@example.com',
  'Test User',
  './profile_pics/unknown.jpg'
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE username = 'testuser'
  );
-- demo users (kept distinct emails for unique key)
INSERT INTO users (username, email, display_name, profile_picture_url)
VALUES (
    'assaf',
    'assaf@example.com',
    'Assaf Levy',
    './profile_pics/assaf.jpg'
  ),
  (
    'beng',
    'beng@example.com',
    'בן גולדמן',
    './profile_pics/ben100.jpg'
  ),
  (
    'daniell',
    'daniell@example.com',
    'דניאל לומט',
    './profile_pics/daniell.jpg'
  ),
  (
    'yonir',
    'yonir@example.com',
    'יוני ריפ',
    './profile_pics/yonir.jpg'
  ),
  (
    'seanp',
    'seanp@example.com',
    'שון פיפקין',
    './profile_pics/seanp.jpg'
  ),
  (
    'danils',
    'danils@example.com',
    'דנייל',
    './profile_pics/unknown.jpg'
  ),
  (
    'yonatanb',
    'yonatanb@example.com',
    'יונתן ברוך',
    './profile_pics/yonatanb.jpg'
  ),
  (
    'igalh',
    'igalh@example.com',
    'יגאל',
    './profile_pics/igalh.jpg'
  ),
  (
    'adib',
    'adib@example.com',
    'עדי ב.',
    './profile_pics/unknown.jpg'
  ),
  (
    'ronb',
    'ronb@example.com',
    'רון',
    './profile_pics/unknown.jpg'
  ),
  (
    'pessia',
    'pessia@example.com',
    'פסיה',
    './profile_pics/unknown.jpg'
  ),
  (
    'mazy',
    'mazy@example.com',
    'מזי מאזדה',
    './profile_pics/unknown.jpg'
  ),
  (
    'gotlib',
    'gotlib@example.com',
    'גוטליב',
    './profile_pics/gotlib.jpg'
  ),
  (
    'yarin',
    'yarin@example.com',
    'ירין בארי',
    './profile_pics/yarin.jpg'
  ),
  (
    'avrahamk',
    'avrahamk@example.com',
    'אברהם קורן',
    './profile_pics/avrahamk.jpg'
  ),
  (
    'noams',
    'noams@example.com',
    'נועם שוחט',
    './profile_pics/noams.jpg'
  ) ON DUPLICATE KEY
UPDATE email =
VALUES(email),
  display_name =
VALUES(display_name),
  profile_picture_url =
VALUES(profile_picture_url);
-- handy user ids
SET @assaf := (
    SELECT id
    FROM users
    WHERE username = 'assaf'
  );
SET @beng := (
    SELECT id
    FROM users
    WHERE username = 'beng'
  );
SET @daniell := (
    SELECT id
    FROM users
    WHERE username = 'daniell'
  );
SET @yonir := (
    SELECT id
    FROM users
    WHERE username = 'yonir'
  );
SET @seanp := (
    SELECT id
    FROM users
    WHERE username = 'seanp'
  );
-- contacts (unique per owner+contact_user thanks to uniq_owner_contactuser)
INSERT INTO contacts (
    owner_user_id,
    contact_user_id,
    contact_name,
    profile_picture_url
  )
VALUES (
    @assaf,
    (
      SELECT id
      FROM users
      WHERE username = 'beng'
    ),
    'בן גולדמן',
    './profile_pics/ben100.jpg'
  ),
  (
    @beng,
    (
      SELECT id
      FROM users
      WHERE username = 'assaf'
    ),
    'אסף לוי',
    './profile_pics/assaf.jpg'
  ),
  (
    @assaf,
    (
      SELECT id
      FROM users
      WHERE username = 'daniell'
    ),
    'דניאל לומט',
    './profile_pics/daniell.jpg'
  ),
  (
    @beng,
    (
      SELECT id
      FROM users
      WHERE username = 'daniell'
    ),
    'דניאלוש',
    './profile_pics/daniell.jpg'
  ) ON DUPLICATE KEY
UPDATE contact_name =
VALUES(contact_name),
  profile_picture_url =
VALUES(profile_picture_url);
-- sample chat between beng(owner) and assaf
SET @owner := @beng;
SET @peer := (
    SELECT id
    FROM users
    WHERE username = 'assaf'
  );
SET @cid := (
    SELECT id
    FROM contacts
    WHERE owner_user_id = @owner
      AND contact_user_id = @peer
    LIMIT 1
  );
INSERT INTO messages (
    owner_user_id,
    contact_id,
    msg_datetime,
    is_from_owner,
    msg_type,
    msg_body
  )
VALUES (
    @owner,
    @cid,
    '2025-07-29 22:27:17',
    1,
    'text',
    'אהלן אסף!'
  ),
  (
    @owner,
    @cid,
    '2025-07-29 22:27:52',
    0,
    'text',
    'שלום שלום, מה נשמע?'
  );
