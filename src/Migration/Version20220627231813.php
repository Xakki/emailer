<?php

declare(strict_types=1);

namespace Xakki\Emailer\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220627231813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table initialization';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
create table project
(
    id      int unsigned auto_increment primary key,
    name    varchar(150)                                   not null,
    token   varchar(32)                                    not null,
    params  text                                           not null,
    status  enum ('off', 'on') default 'on',
    created timestamp          default current_timestamp() not null
) engine = InnoDB
  charset = utf8;


create table domain
(
    id      int unsigned auto_increment primary key,
    name    varchar(128)                                     not null,
    status  enum ('bad', 'good') default 'good',
    parent  int                  default 0                   not null comment 'if domain is CNAME or Subdomain',
    created timestamp            default current_timestamp() not null,
    constraint ux_domain unique (name)
) engine = InnoDB
  charset = utf8;

create index ix_domain_parent on domain (parent);
create index ix_domain_status on domain (status);


create table email
(
    id         bigint unsigned auto_increment primary key,
    email      varchar(255)                                   not null,
    name       varchar(255)       default ''                  not null,
    status     enum ('off', 'on') default 'on',
    created    timestamp          default current_timestamp() not null,
    cnt_send   int                default 0                   not null,
    cnt_read   int                default 0                   not null,
    domain_id  int unsigned                                   not null,
    project_id int unsigned                                   not null,
    constraint ux_email unique (project_id, email),
    FOREIGN KEY (project_id) REFERENCES project (id),
    FOREIGN KEY (domain_id) REFERENCES domain (id)
) engine = InnoDB
  charset = utf8;

create index ix_email_project_status on email (project_id, status);

create table notify
(
    id         int unsigned auto_increment primary key,
    created    timestamp default current_timestamp() not null,
    name       varchar(128)                          not null,
    project_id int unsigned                          not null,
    constraint ux_project_name unique (project_id, name),
    FOREIGN KEY (project_id) REFERENCES project (id)
) engine = InnoDB
  charset = utf8;


create table subscribe
(
    id         bigint unsigned auto_increment primary key,
    notify_id  int unsigned    not null,
    project_id int unsigned    not null,
    email_id   bigint unsigned not null,
    `period`     int unsigned default 600,
    status  enum ('off', 'on') default 'on',
    created    timestamp          default current_timestamp() not null,
    constraint ux_subscribe unique (project_id, email_id, notify_id),
    FOREIGN KEY (project_id) REFERENCES project (id),
    FOREIGN KEY (email_id) REFERENCES email (id),
    FOREIGN KEY (notify_id) REFERENCES notify (id)
) engine = InnoDB
  charset = utf8;

create table transport
(
    id         int unsigned auto_increment primary key,
    status     enum ('off', 'on') default 'on'                not null,
    created    timestamp          default current_timestamp() not null,
    params     text                                           not null,
    limit_day  int                default 0                   not null,
    cnt_day    int                default 0                   not null,
    domain_id  int unsigned       default                     null,
    project_id int unsigned                                   not null,
    FOREIGN KEY (project_id) REFERENCES project (id),
    FOREIGN KEY (domain_id) REFERENCES domain (id) on delete set null
) engine = InnoDB
  charset = utf8;


create table tpl
(
    id         int unsigned auto_increment primary key,
    created    timestamp          default current_timestamp() not null,
    name       varchar(128)                                   not null,
    html       mediumtext                                     null,
    status     enum ('off', 'on') default 'on'                not null,
    type       enum ('wraper', 'content', 'block')            not null,
    project_id int unsigned                                   not null,
    FOREIGN KEY (project_id) REFERENCES project (id)
) engine = InnoDB
  charset = utf8;
create index ix_tpl_project on tpl (project_id, status, type);

create table tpl_rev
(
    id         int unsigned auto_increment primary key,
    created    timestamp default current_timestamp() not null,
    name       varchar(128)                          not null,
    html       mediumtext                            null,
    type       enum ('wraper', 'content')            not null,
    base_id    int unsigned                          not null,
    project_id int unsigned                          not null,
    FOREIGN KEY (project_id) REFERENCES project (id),
    FOREIGN KEY (base_id) REFERENCES tpl (id)
) engine = InnoDB
  charset = utf8;

create index ix_tpl_rev_type on tpl_rev (type);

create table campaign
(
    id             int unsigned auto_increment primary key,
    created        timestamp  default current_timestamp()         not null,
    finished       timestamp                                      null,
    status         enum ('off', 'on') default 'on',
    name           varchar(128)                                   not null comment 'subject mail',
    limit_day      int unsigned       default 0                   not null,
    cnt_send       int unsigned       default 0                   not null,
    cnt_queue      int unsigned       default 0                   not null,
    replacers      text                                           null,
    transport_id   int unsigned                                   null,
    notify_id      int unsigned                                   not null,
    tpl_wraper_id  int unsigned                                   not null,
    tpl_content_id int unsigned                                   not null,
    project_id     int unsigned                                   not null,
    FOREIGN KEY (transport_id) REFERENCES transport (id),
    FOREIGN KEY (notify_id) REFERENCES notify (id),
    FOREIGN KEY (tpl_wraper_id) REFERENCES tpl (id),
    FOREIGN KEY (tpl_content_id) REFERENCES tpl (id),
    FOREIGN KEY (project_id) REFERENCES project (id)
) engine = InnoDB
  charset = utf8;


create table queue
(
    id         bigint unsigned auto_increment primary key,
    created    timestamp  default current_timestamp() not null,
    sended     timestamp                              null,
    readed     timestamp                              null,
    unsubs     timestamp                              null,
    status     tinyint(1) default 0                   not null,
    retry      tinyint(1) default 0                   not null,
    campaign_id int unsigned                           not null,
    email_id   bigint unsigned                        not null,
    project_id int unsigned                           not null,
    notify_id  int unsigned                           not null,
    FOREIGN KEY (campaign_id) REFERENCES campaign (id),
    FOREIGN KEY (email_id) REFERENCES email (id),
    FOREIGN KEY (notify_id) REFERENCES notify (id),
    FOREIGN KEY (project_id) REFERENCES project (id)
) engine = InnoDB
  charset = utf8;

create index ix_queue_status on queue (status);


create table queue_data
(
    id           bigint unsigned not null,
    data         text            not null,
    last_error   varchar(255)    null,
    transport_id int unsigned    null,
    constraint ux_id unique (id),
    FOREIGN KEY (id) REFERENCES queue (id),
    FOREIGN KEY (transport_id) REFERENCES transport (id)
) engine = InnoDB
  charset = utf8;


create table browser
(
    id bigint unsigned auto_increment primary key,
    ua varchar(255) not null,
    constraint ux_ua unique (ua)
) engine = InnoDB
  charset = utf8;


create table stats
(
    id         bigint unsigned auto_increment primary key,
    created    timestamp default current_timestamp() not null,
    uri_ref    varchar(255)                          null,
    domain_id  int    unsigned                       null,
    queue_id   bigint unsigned                       not null,
    browser_id bigint unsigned                       not null,
    project_id int unsigned                          not null,
    action     int unsigned                          not null,
    FOREIGN KEY (project_id) REFERENCES project (id),
    FOREIGN KEY (queue_id) REFERENCES queue (id),
    FOREIGN KEY (domain_id) REFERENCES domain (id),
    FOREIGN KEY (browser_id) REFERENCES browser (id)
) engine = InnoDB
  charset = utf8;

create index ix_stats_created on stats (project_id, queue_id);
        ");
    }

}
