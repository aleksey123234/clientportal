CREATE TABLE IF NOT EXISTS court_directory_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    region_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(1000) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_court_source_name (name),
    CONSTRAINT fk_court_source_region FOREIGN KEY (region_id) REFERENCES geographic_regions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO court_directory_sources (region_id, name, url)
SELECT gr.id, src.name, src.url
FROM (
    SELECT 'CA' country_code, 'AB' code, 'AB Provincial' name, 'https://albertacourts.ca/contact-us/contact-the-courts' url UNION ALL
    SELECT 'CA','AB','AB Court of King''s Bench','https://albertacourts.ca/court-of-queens-bench/locations-sittings' UNION ALL
    SELECT 'CA','BC','BC Provincial','http://www.provincialcourt.bc.ca/locations-contacts' UNION ALL
    SELECT 'CA','BC','BC Supreme','http://www.courts.gov.bc.ca/supreme_court/court_locations_and_contacts.aspx' UNION ALL
    SELECT 'CA','MB','MB Provincial','http://www.manitobacourts.mb.ca/provincial-court/locations-and-contact-info/#pr_locations' UNION ALL
    SELECT 'CA','MB','MB Court of King''s Bench','http://www.manitobacourts.mb.ca/court-of-queens-bench/location-and-contact-info/' UNION ALL
    SELECT 'CA','NB','NB Provincial','http://www.gnb.ca/cour/06PCNB/locations-e.asp' UNION ALL
    SELECT 'CA','NB','NB Court of King''s Bench','http://www.gnb.ca/cour/04CQB/locations-e.asp' UNION ALL
    SELECT 'CA','NL','NL Provincial','http://www.court.nl.ca/provincial/about/locations.html' UNION ALL
    SELECT 'CA','NL','NL Supreme','http://www.court.nl.ca/supreme/contact.html' UNION ALL
    SELECT 'CA','NS','NS Provincial and Supreme','http://www.courts.ns.ca/Courthouse_Locations/Courthouse_Locations_Map.htm' UNION ALL
    SELECT 'CA','NT','NT Territorial and Supreme','https://www.nwtcourts.ca/' UNION ALL
    SELECT 'CA','NU','NU Territorial and Supreme','http://www.nunavutcourts.ca/hours-and-location' UNION ALL
    SELECT 'CA','ON','ON Court email addresses','https://www.ontariocourts.ca/ocj/covid-19/courthouse-email-addresses/' UNION ALL
    SELECT 'CA','ON','ON Court Search','http://www.attorneygeneral.jus.gov.on.ca/english/courts/Court_Addresses/' UNION ALL
    SELECT 'CA','ON','ON Provincial and Superior','http://www.attorneygeneral.jus.gov.on.ca/english/courts/Court_Addresses/default_accessible.php#A' UNION ALL
    SELECT 'CA','PE','PE Provincial and Supreme','http://www.courts.pe.ca/index.php?number=1051097' UNION ALL
    SELECT 'CA','QC','QC Provincial','http://www.justice.gouv.qc.ca/english/joindre/palais/palais-a.htm' UNION ALL
    SELECT 'CA','QC','QC Superior','http://www.justice.gouv.qc.ca/english/joindre/palais/itinerant/itinerant-a.htm' UNION ALL
    SELECT 'CA','SK','SK Provincial','http://sasklawcourts.ca/index.php/home/provincial-court/court-locations-and-sitting-times/provincial-court-offices' UNION ALL
    SELECT 'CA','SK','SK Court of King''s Bench','http://sasklawcourts.ca/index.php/home/court-of-queen-s-bench/court-locations-and-sitting-times' UNION ALL
    SELECT 'US','AK','USA: Alaska','https://records.courts.alaska.gov/eaccess/home.page.20' UNION ALL
    SELECT 'US','NY','USA: New York','http://www.nycourts.gov/courts/index.shtml' UNION ALL
    SELECT 'CA','YT','YT Territorial','http://www.yukoncourts.ca/courts/territorial/contact.html' UNION ALL
    SELECT 'CA','YT','YT Supreme','http://www.yukoncourts.ca/courts/supreme/contact.html'
) src
JOIN geographic_regions gr ON gr.country_code = src.country_code AND gr.code = src.code
ON DUPLICATE KEY UPDATE region_id = VALUES(region_id), url = VALUES(url), is_active = 1;
