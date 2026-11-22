-- Reside Kenya — Postgres schema (converted from the original MySQL schema).
-- Integer flags stay 0/1 smallints so the PHP code works unchanged.

CREATE OR REPLACE FUNCTION field(anyelement, VARIADIC anyarray) RETURNS integer
LANGUAGE sql IMMUTABLE AS $$ SELECT COALESCE(array_position($2, $1), 0) $$;

CREATE OR REPLACE FUNCTION touch_updated_at() RETURNS trigger
LANGUAGE plpgsql AS $$ BEGIN NEW.updated_at = NOW(); RETURN NEW; END $$;

CREATE TABLE IF NOT EXISTS users (
  id serial PRIMARY KEY,
  name varchar(120) NOT NULL,
  email varchar(190) NOT NULL UNIQUE,
  password_hash varchar(255) NOT NULL,
  role varchar(20) NOT NULL DEFAULT 'agent',
  phone varchar(30),
  avatar varchar(255),
  bio text,
  is_active smallint NOT NULL DEFAULT 1,
  last_login_at timestamp,
  remember_token varchar(100),
  created_at timestamp NOT NULL DEFAULT NOW(),
  updated_at timestamp NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

CREATE TABLE IF NOT EXISTS activity_log (
  id serial PRIMARY KEY,
  user_id integer REFERENCES users(id) ON DELETE SET NULL,
  action varchar(120) NOT NULL,
  entity varchar(60),
  entity_id integer,
  ip_address varchar(45),
  created_at timestamp NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_log_user ON activity_log(user_id);

CREATE TABLE IF NOT EXISTS agents (
  id serial PRIMARY KEY,
  user_id integer REFERENCES users(id) ON DELETE SET NULL,
  name varchar(120) NOT NULL,
  title varchar(120),
  email varchar(190),
  phone varchar(30),
  whatsapp varchar(30),
  photo varchar(255),
  bio text,
  is_active smallint NOT NULL DEFAULT 1,
  created_at timestamp NOT NULL DEFAULT NOW(),
  updated_at timestamp NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_agents_user ON agents(user_id);

CREATE TABLE IF NOT EXISTS amenities (
  id serial PRIMARY KEY,
  name varchar(80) NOT NULL UNIQUE,
  icon varchar(80)
);

CREATE TABLE IF NOT EXISTS categories (
  id serial PRIMARY KEY,
  name varchar(80) NOT NULL,
  slug varchar(90) NOT NULL UNIQUE,
  icon varchar(80),
  image varchar(255),
  description varchar(255),
  sort_order integer NOT NULL DEFAULT 0,
  is_active smallint NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS counties (
  id serial PRIMARY KEY,
  name varchar(80) NOT NULL,
  slug varchar(90) NOT NULL UNIQUE,
  image varchar(255),
  sort_order integer NOT NULL DEFAULT 0,
  is_active smallint NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS towns (
  id serial PRIMARY KEY,
  county_id integer NOT NULL REFERENCES counties(id) ON DELETE CASCADE,
  name varchar(120) NOT NULL,
  slug varchar(140) NOT NULL,
  UNIQUE (county_id, slug)
);
CREATE INDEX IF NOT EXISTS idx_towns_county ON towns(county_id);

CREATE TABLE IF NOT EXISTS estates (
  id serial PRIMARY KEY,
  town_id integer NOT NULL REFERENCES towns(id) ON DELETE CASCADE,
  name varchar(140) NOT NULL,
  slug varchar(160) NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_estates_town ON estates(town_id);

CREATE TABLE IF NOT EXISTS properties (
  id serial PRIMARY KEY,
  ref_code varchar(30) NOT NULL UNIQUE,
  title varchar(190) NOT NULL,
  slug varchar(210) NOT NULL UNIQUE,
  description text,
  purpose varchar(20) NOT NULL DEFAULT 'sale',
  category_id integer REFERENCES categories(id) ON DELETE SET NULL,
  status varchar(20) NOT NULL DEFAULT 'available',
  currency varchar(6) NOT NULL DEFAULT 'KES',
  price numeric(15,2),
  sale_price numeric(15,2),
  rent_price numeric(15,2),
  rent_period varchar(10) DEFAULT 'month',
  county_id integer REFERENCES counties(id) ON DELETE SET NULL,
  town_id integer REFERENCES towns(id) ON DELETE SET NULL,
  estate_id integer REFERENCES estates(id) ON DELETE SET NULL,
  address varchar(255),
  latitude numeric(10,7),
  longitude numeric(10,7),
  bedrooms smallint,
  bathrooms smallint,
  parking_spaces smallint,
  kitchens smallint,
  living_rooms smallint,
  floors smallint,
  year_built smallint,
  area_sqm numeric(12,2),
  land_size_sqm numeric(12,2),
  featured_image varchar(255),
  virtual_tour_url varchar(255),
  tour_360_url varchar(255),
  youtube_url varchar(255),
  brochure_pdf varchar(255),
  nearby_schools text,
  nearby_hospitals text,
  nearby_shopping text,
  features text,
  agent_id integer REFERENCES agents(id) ON DELETE SET NULL,
  owner_name varchar(120),
  owner_contact varchar(120),
  is_featured smallint NOT NULL DEFAULT 0,
  is_luxury smallint NOT NULL DEFAULT 0,
  airbnb_max_guests smallint,
  airbnb_cleaning_fee numeric(12,2),
  airbnb_house_rules text,
  meta_title varchar(190),
  meta_description varchar(300),
  seo_keywords varchar(255),
  views integer NOT NULL DEFAULT 0,
  source_url varchar(500),
  expires_at date,
  created_by integer REFERENCES users(id) ON DELETE SET NULL,
  created_at timestamp NOT NULL DEFAULT NOW(),
  updated_at timestamp NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_prop_purpose ON properties(purpose);
CREATE INDEX IF NOT EXISTS idx_prop_status ON properties(status);
CREATE INDEX IF NOT EXISTS idx_prop_category ON properties(category_id);
CREATE INDEX IF NOT EXISTS idx_prop_county ON properties(county_id);
CREATE INDEX IF NOT EXISTS idx_prop_town ON properties(town_id);
CREATE INDEX IF NOT EXISTS idx_prop_price ON properties(price);
CREATE INDEX IF NOT EXISTS idx_prop_featured ON properties(is_featured);

CREATE TABLE IF NOT EXISTS property_amenities (
  property_id integer NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
  amenity_id integer NOT NULL REFERENCES amenities(id) ON DELETE CASCADE,
  PRIMARY KEY (property_id, amenity_id)
);

CREATE TABLE IF NOT EXISTS property_images (
  id serial PRIMARY KEY,
  property_id integer NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
  file_path varchar(255) NOT NULL,
  alt_text varchar(190),
  is_floorplan smallint NOT NULL DEFAULT 0,
  sort_order integer NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_img_property ON property_images(property_id);

CREATE TABLE IF NOT EXISTS open_houses (
  id serial PRIMARY KEY,
  property_id integer NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
  start_at timestamp NOT NULL,
  end_at timestamp NOT NULL
);

CREATE TABLE IF NOT EXISTS inquiries (
  id serial PRIMARY KEY,
  property_id integer REFERENCES properties(id) ON DELETE SET NULL,
  type varchar(20) NOT NULL DEFAULT 'general',
  name varchar(120) NOT NULL,
  email varchar(190),
  phone varchar(30),
  message text,
  preferred_date date,
  is_read smallint NOT NULL DEFAULT 0,
  ip_address varchar(45),
  created_at timestamp NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_inq_read ON inquiries(is_read);

CREATE TABLE IF NOT EXISTS bookings (
  id serial PRIMARY KEY,
  property_id integer NOT NULL REFERENCES properties(id) ON DELETE CASCADE,
  guest_name varchar(120) NOT NULL,
  guest_email varchar(190),
  guest_phone varchar(30),
  guests smallint NOT NULL DEFAULT 1,
  check_in date NOT NULL,
  check_out date NOT NULL,
  nights smallint,
  total_amount numeric(12,2),
  status varchar(20) NOT NULL DEFAULT 'pending',
  notes text,
  created_at timestamp NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS submissions (
  id serial PRIMARY KEY,
  full_name varchar(120) NOT NULL,
  phone varchar(30) NOT NULL,
  email varchar(190),
  county_id integer REFERENCES counties(id) ON DELETE SET NULL,
  county_text varchar(80),
  property_type varchar(80),
  property_status varchar(20) NOT NULL DEFAULT 'selling',
  description text,
  bedrooms smallint,
  bathrooms smallint,
  location varchar(255),
  expected_price numeric(15,2),
  maps_url varchar(255),
  preferred_contact varchar(20) DEFAULT 'phone',
  photos_json text,
  status varchar(20) NOT NULL DEFAULT 'new',
  ip_address varchar(45),
  created_at timestamp NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS subscribers (
  id serial PRIMARY KEY,
  email varchar(190) NOT NULL UNIQUE,
  created_at timestamp NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS testimonials (
  id serial PRIMARY KEY,
  name varchar(120) NOT NULL,
  role varchar(120),
  photo varchar(255),
  rating smallint NOT NULL DEFAULT 5,
  content text NOT NULL,
  is_active smallint NOT NULL DEFAULT 1,
  sort_order integer NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS settings (
  setting_key varchar(80) PRIMARY KEY,
  setting_value text
);

CREATE TABLE IF NOT EXISTS rate_limits (
  id serial PRIMARY KEY,
  bucket varchar(120) NOT NULL,
  hits integer NOT NULL DEFAULT 1,
  window_start timestamp NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_rl_bucket ON rate_limits(bucket);

CREATE TABLE IF NOT EXISTS push_subscriptions (
  id serial PRIMARY KEY,
  user_id integer REFERENCES users(id) ON DELETE CASCADE,
  endpoint text NOT NULL UNIQUE,
  p256dh text NOT NULL,
  auth text NOT NULL,
  user_agent varchar(255),
  created_at timestamp NOT NULL DEFAULT NOW(),
  last_ok_at timestamp
);

CREATE TABLE IF NOT EXISTS sessions (
  id varchar(128) PRIMARY KEY,
  data text NOT NULL DEFAULT '',
  updated_at timestamptz NOT NULL DEFAULT NOW()
);

DROP TRIGGER IF EXISTS trg_users_upd ON users;
CREATE TRIGGER trg_users_upd BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
DROP TRIGGER IF EXISTS trg_agents_upd ON agents;
CREATE TRIGGER trg_agents_upd BEFORE UPDATE ON agents FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
DROP TRIGGER IF EXISTS trg_properties_upd ON properties;
CREATE TRIGGER trg_properties_upd BEFORE UPDATE ON properties FOR EACH ROW EXECUTE FUNCTION touch_updated_at();
