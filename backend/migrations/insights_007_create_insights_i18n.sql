-- insights_007_create_insights_i18n.sql
-- Per-locale translatable fields for insights. Mirrors projects_i18n /
-- events_i18n (PR 5 i18n milestone). title/excerpt/content move here so
-- that the public site can render insights in fi_FI / en_GB / sw_TZ.
CREATE TABLE insights_i18n (
    insight_id  CHAR(36)     NOT NULL,
    locale      VARCHAR(10)  NOT NULL,
    title       VARCHAR(255) NOT NULL,
    excerpt     TEXT         NOT NULL,
    content     LONGTEXT     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (insight_id, locale),
    CONSTRAINT fk_insights_i18n_insight FOREIGN KEY (insight_id) REFERENCES insights(id) ON DELETE CASCADE,
    FULLTEXT INDEX ft_title_excerpt (title, excerpt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
