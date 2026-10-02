SELECT c.name AS career_name, COUNT(DISTINCT lj.job_offer_id) AS total_ofertas
FROM careers c
JOIN (SELECT DISTINCT cc.career_id, cl.language_id
      FROM career_course cc JOIN course_language cl ON cl.course_id = cc.course_id) e ON e.career_id = c.id
JOIN language_job lj ON lj.language_id = e.language_id
GROUP BY c.id, c.name
ORDER BY total_ofertas DESC
LIMIT 5
