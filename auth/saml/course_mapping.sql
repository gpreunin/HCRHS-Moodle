/* Previusly required
CREATE DATABASE course_mapping;
GRANT ALL ON course_mapping.* TO moodleuser;
*/

CREATE DATABASE saml_course_mapping;

GRANT ALL ON saml_course_mapping.* to moodleuser identified by 'moodlepass';

USE saml_course_mapping;

CREATE TABLE course_mapping (
    course_mapping_id	INTEGER AUTO_INCREMENT UNIQUE NOT NULL,
    saml_course_id      varchar(20) NOT NULL,
    saml_course_period  int(4) NOT NULL,
    lms_course_id    text NOT NULL,
    PRIMARY KEY(course_mapping_id)
);

CREATE TABLE rol_mapping (
    saml_role   varchar(20) NOT NULL,
    lms_role    varchar(20) NOT NULL,
    PRIMARY KEY (saml_role)
);