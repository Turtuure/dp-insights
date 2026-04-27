-- insights_009_drop_translated_columns.sql
-- A11-equivalent for insights: insights_i18n is now the sole source of
-- truth for translated content. Backfill happened in 008; this drop is
-- irreversible.
--
-- The legacy FULLTEXT index ft_title_body referenced (title, search_text);
-- it must be dropped together with the title column. SqlSearchRepository's
-- searchInsights branch is rewritten to use insights_i18n.ft_title_excerpt
-- (added in 007) and JOIN insights_i18n × supported locales.
--
-- search_text stays for now: it is fi_FI-derived plain text of the body and
-- could still be useful as a tenant-internal tooling backstop. SqlInsightRepository::save()
-- is updated to populate it from the fi_FI translation row.
ALTER TABLE insights
    DROP INDEX ft_title_body;

ALTER TABLE insights
    DROP COLUMN title,
    DROP COLUMN excerpt,
    DROP COLUMN content;
