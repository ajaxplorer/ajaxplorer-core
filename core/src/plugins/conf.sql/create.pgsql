CREATE TABLE ajxp_user_rights (
  login varchar(255) NOT NULL,
  repo_uuid varchar(33) NOT NULL,
  rights text NOT NULL,
  PRIMARY KEY(login, repo_uuid)
);

CREATE TABLE ajxp_user_prefs (
  login varchar(255) NOT NULL,
  name varchar(255) NOT NULL,
  val bytea,
  PRIMARY KEY(login, name)
);

CREATE TABLE ajxp_user_bookmarks (
  login varchar(255) NOT NULL,
  repo_uuid varchar(33) NOT NULL,
  path varchar(255),
  title varchar(255) NOT NULL,
  PRIMARY KEY(login, repo_uuid, title)
);

CREATE TABLE ajxp_repo (
  uuid varchar(33) PRIMARY KEY,
  parent_uuid varchar(33) default NULL,
  owner_user_id varchar(50) default NULL,
  child_user_id varchar(50) default NULL,
  path varchar(255),
  display varchar(255),
  "accessType" varchar(20),
  recycle varchar(255),
  bcreate BOOLEAN,
  writeable BOOLEAN,
  enabled BOOLEAN,
  "isTemplate" BOOLEAN,
  "inferOptionsFromParent" BOOLEAN,
  slug varchar(255),
  "groupPath" varchar(255)
);

CREATE TABLE ajxp_repo_options (
  uuid varchar(33) NOT NULL,
  name varchar(50) NOT NULL,
  val bytea,
  PRIMARY KEY (uuid, name)
);

CREATE TABLE ajxp_roles (
  role_id varchar(255) PRIMARY KEY,
  serial_role bytea NOT NULL,
  searchable_repositories text
);

CREATE TABLE ajxp_groups (
  "groupPath" varchar(255) PRIMARY KEY,
  "groupLabel" varchar(255) NOT NULL
);

CREATE TABLE ajxp_plugin_configs (
  id varchar(50) PRIMARY KEY,
  configs bytea NOT NULL
);

CREATE TABLE ajxp_simple_store (
  object_id varchar(255) NOT NULL,
  store_id varchar(50) NOT NULL,
  serialized_data text,
  binary_data bytea,
  related_object_id varchar(255),
  insertion_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY(object_id, store_id)
);

CREATE TABLE ajxp_user_teams (
  team_id VARCHAR(255) NOT NULL,
  user_id varchar(255) NOT NULL,
  team_label VARCHAR(255) NOT NULL,
  owner_id varchar(255) NOT NULL,
  PRIMARY KEY(team_id, user_id)
);
