SELECT c.name AS career_name, COUNT(*) AS total_ofertas
FROM careers c
JOIN (
    SELECT t.career_id, t.job_offer_id
    FROM (
        SELECT e.career_id, tj.job_offer_id
        FROM (SELECT DISTINCT cc.career_id, ct.technology_id
              FROM career_course cc JOIN course_technology ct ON ct.course_id = cc.course_id) e
        JOIN technology_job tj ON tj.technology_id = e.technology_id
        UNION
        SELECT e.career_id, lj.job_offer_id
        FROM (SELECT DISTINCT cc.career_id, cl.language_id
              FROM career_course cc JOIN course_language cl ON cl.course_id = cc.course_id) e
        JOIN language_job lj ON lj.language_id = e.language_id
    ) t
) x ON x.career_id = c.id
GROUP BY c.id, c.name
ORDER BY total_ofertas DESC
