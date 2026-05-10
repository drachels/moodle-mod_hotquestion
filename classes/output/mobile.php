<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_hotquestion\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/hotquestion/locallib.php');

/**
 * Mobile output class for the Moodle App.
 *
 * @package   mod_hotquestion
 * @copyright 2026 AL Rachels <drachels@drachels.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Mobile course module view — shows the question list.
     *
     * @param array $args Incoming app args.
     * @return array
     */
    public static function mobile_course_view($args) {
        global $DB, $OUTPUT, $USER;

        $cmid = (int)($args['cmid'] ?? 0);
        $cm = get_coursemodule_from_id('hotquestion', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $hotquestion = $DB->get_record('hotquestion', ['id' => $cm->instance], '*', MUST_EXIST);
        $modulecontext = \context_module::instance($cm->id, MUST_EXIST);
        /** @var \context $context */
        $context = \context::instance_by_id($modulecontext->id, MUST_EXIST);

        require_login($course, false, $cm);
        require_capability('mod/hotquestion:view', $context);

        $canask = has_capability('mod/hotquestion:ask', $context);
        $canvote = has_capability('mod/hotquestion:vote', $context);
        $canrate = has_capability('mod/hotquestion:rate', $context);
        $canmanageentries = has_capability('mod/hotquestion:manageentries', $context);
        $canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);
        $ismanagerorrater = $canmanageentries || $canrate;

        $groupmode = groups_get_activity_groupmode($cm);
        $selectedgroup = array_key_exists('groupid', $args) ? (int)$args['groupid'] : groups_get_activity_group($cm, true);
        $selectedgroup = max(0, $selectedgroup);
        $groupoptions = [];
        $allowallparticipants = ($groupmode == VISIBLEGROUPS) || $canaccessallgroups;
        $allowedgroupids = [];

        if ($groupmode != NOGROUPS) {
            $groups = $canaccessallgroups
                ? groups_get_all_groups($course->id, 0, $cm->groupingid, 'g.id, g.name')
                : groups_get_all_groups($course->id, $USER->id, $cm->groupingid, 'g.id, g.name');

            if (!empty($groups)) {
                foreach ($groups as $group) {
                    $allowedgroupids[] = (int)$group->id;
                }
            }

            if ($selectedgroup > 0 && !in_array($selectedgroup, $allowedgroupids)) {
                $selectedgroup = $allowallparticipants ? 0 : ((int)reset($allowedgroupids) ?: 0);
            }

            if (!$allowallparticipants && $selectedgroup === 0) {
                $selectedgroup = ((int)reset($allowedgroupids) ?: 0);
            }

            if (!array_key_exists('groupid', $args) && $allowallparticipants && $ismanagerorrater) {
                $selectedgroup = 0;
            }

            if ($allowallparticipants) {
                $groupoptions[] = [
                    'id' => 0,
                    'name' => get_string('allparticipants'),
                    'selected' => ($selectedgroup === 0),
                ];
            }

            if (!empty($groups)) {
                foreach ($groups as $group) {
                    $groupoptions[] = [
                        'id' => (int)$group->id,
                        'name' => format_string($group->name),
                        'selected' => ((int)$group->id === $selectedgroup),
                    ];
                }
            }

        }

        $roundid = isset($args['roundid']) ? (int)$args['roundid'] : -1;
        if ($roundid <= 0) {
            $roundid = -1;
        }
        $hq = new \mod_hotquestion($cmid, $roundid);

        $userroundpostcount = 0;
        $showonlyown = false;
        $minquestionsview = (int)($hotquestion->minquestionsview ?? 0);
        if ($canask) {
            $userroundpostcount = $hq->get_user_question_count_in_current_round($USER->id);
            if (!$ismanagerorrater && ($minquestionsview > 0) && ($userroundpostcount < $minquestionsview)) {
                $showonlyown = true;
            }
        }

        $action = clean_param((string)($args['action'] ?? ''), PARAM_ALPHANUMEXT);
        $questionid = isset($args['q']) ? (int)$args['q'] : 0;
        $priorityup = isset($args['u']) ? (int)$args['u'] : 1;
        $isopen = \mod_hotquestion\local\hqavailable::is_hotquestion_active($hq);
        $canvoteclosed = !$isopen && !$hotquestion->viewaftertimeclose;

        if (!empty($action)) {
            switch ($action) {
                case 'tpriority':
                    if ($canrate && $questionid > 0) {
                        $hq->tpriority_change($priorityup, $questionid);
                    }
                    break;
                case 'vote':
                    if ($canvote && !$showonlyown && $questionid > 0 && ($isopen || $canrate || $canvoteclosed)) {
                        $hq->vote_on($questionid);
                    }
                    break;
                case 'removevote':
                    if ($canvote && !$showonlyown && $questionid > 0 && ($isopen || $canrate || $canvoteclosed)) {
                        $hq->remove_vote($questionid);
                    }
                    break;
                case 'approve':
                    if (($canmanageentries || $canrate) && $questionid > 0) {
                        $hq->approve_question($questionid);
                    }
                    break;
                case 'remove':
                    if ($canmanageentries && $questionid > 0) {
                        $_REQUEST['q'] = $questionid;
                        $_GET['q'] = $questionid;
                        $hq->remove_question($questionid);
                    }
                    break;
                case 'newround':
                    if ($canrate || $canmanageentries) {
                        $hq->add_new_round();
                        $roundid = -1;
                    }
                    break;
                case 'removeround':
                    if ($canmanageentries && !empty($hq->get_currentround()->id)) {
                        $_REQUEST['round'] = (int)$hq->get_currentround()->id;
                        $_GET['round'] = (int)$hq->get_currentround()->id;
                        $hq->remove_round();
                        $roundid = -1;
                    }
                    break;
                default:
                    break;
            }
            $hq = new \mod_hotquestion($cmid, $roundid);
            $isopen = \mod_hotquestion\local\hqavailable::is_hotquestion_active($hq);
        }

        $iscurrentround = ($hq->get_nextround() === null);
        $teacherpriorityvisibility = !empty($hotquestion->teacherpriorityvisibility);
        $heatvisibility = ((int)$hotquestion->heatlimit !== 0) && !empty($hotquestion->heatvisibility);
        $remainingvotes = (int)$hq->heat_tally($hq, $USER->id);
        $questions = $hq->get_questions();
        $fs = get_file_storage();
        $questionitems = [];

        foreach ($questions as $question) {
            if (empty($question->approved) && !$canrate && !$canmanageentries) {
                continue;
            }

            if ($showonlyown && ((int)$question->userid !== (int)$USER->id)) {
                continue;
            }

            if ($selectedgroup > 0 && !groups_is_member($selectedgroup, (int)$question->userid)) {
                continue;
            }

            $content = file_rewrite_pluginfile_urls(
                $question->content,
                'pluginfile.php',
                $context->id,
                'mod_hotquestion',
                'question',
                $question->id
            );
            $content = format_text($content, $question->format, [
                'context' => $context,
                'overflowdiv' => true,
            ]);
            $content = self::mobile_strip_unplayable_embedded_webm($content);

            $attachments = [];
            $attachmentpreviews = [];
            $storedfiles = $fs->get_area_files($context->id, 'mod_hotquestion', 'question', $question->id, 'id', false);
            foreach ($storedfiles as $storedfile) {
                $filename = $storedfile->get_filename();
                if ($filename === '.') {
                    continue;
                }

                $isreferencedintext = self::mobile_entry_text_references_file($question->content, $storedfile);

                $mediatype = self::mobile_get_inline_attachment_media_type($storedfile);
                $previewhtml = '';
                if ($mediatype !== '' && !$isreferencedintext && !self::mobile_file_is_webm($storedfile)) {
                    $previewhtml = self::mobile_render_inline_attachment_preview(
                        $mediatype,
                        (string)$storedfile->get_filename(),
                        \moodle_url::make_pluginfile_url(
                            $context->id,
                            'mod_hotquestion',
                            'question',
                            $question->id,
                            $storedfile->get_filepath(),
                            $filename,
                            false
                        ),
                        \moodle_url::make_pluginfile_url(
                            $context->id,
                            'mod_hotquestion',
                            'question',
                            $question->id,
                            $storedfile->get_filepath(),
                            $filename,
                            true
                        ),
                        self::mobile_get_inline_preview_mimetype($storedfile, $mediatype)
                    );
                }

                $fileurl = \moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_hotquestion',
                    'question',
                    $question->id,
                    $storedfile->get_filepath(),
                    $filename
                )->out(false);

                $attachments[] = [
                    'name' => $filename,
                    'url' => $fileurl,
                    'previewhtml' => $previewhtml,
                    'iswebm' => self::mobile_file_is_webm($storedfile),
                    'showwebmhint' => self::mobile_file_is_webm($storedfile),
                ];

                if ($previewhtml !== '') {
                    $attachmentpreviews[] = $previewhtml;
                }
            }

            $author = get_string('anonymous', 'hotquestion');
            if (empty($question->anonymous)) {
                $user = $DB->get_record('user', ['id' => $question->userid], 'id, firstname, lastname', IGNORE_MISSING);
                if ($user) {
                    $author = fullname($user);
                }
            }

            $teacherpriorityup = false;
            $teacherprioritydown = false;
            if ($teacherpriorityvisibility && $canrate) {
                $teacherpriorityup = true;
                $teacherprioritydown = true;
            }

            $voteup = false;
            $removevote = false;
            if ($heatvisibility && $canvote && $iscurrentround && $hq->can_vote_on($question) && ($remainingvotes >= 0)) {
                if (!$hq->has_voted($question->id) && ($remainingvotes >= 1)) {
                    $voteup = true;
                } else if ($hq->has_voted($question->id)) {
                    $removevote = true;
                }
            }

            $questionitems[] = [
                'id'       => (int)$question->id,
                'content'  => $content,
                'author'   => $author,
                'time'     => userdate($question->time),
                'heat'     => (int)$question->votecount,
                'tpriority' => (int)$question->tpriority,
                'approved' => (bool)$question->approved,
                'showtpriority' => $teacherpriorityvisibility,
                'showheat' => $heatvisibility,
                'showremoveapprove' => ($canmanageentries || $canrate),
                'canapprove' => ($canmanageentries || $canrate),
                'canremove' => $canmanageentries,
                'canuppriority' => $teacherpriorityup,
                'candownpriority' => $teacherprioritydown,
                'canvoteup' => $voteup,
                'canremovevote' => $removevote,
                'attachments' => $attachments,
                'hasattachments' => !empty($attachments),
                'hasattachmentspreviews' => !empty($attachmentpreviews),
                'attachmentshtml' => implode('', $attachmentpreviews),
            ];
        }

        $draftitemid = 0;
        if ($canask && $isopen) {
            $draftitemid = file_get_unused_draft_itemid();
        }

        // Compute the display label by matching the selected id, not the 'selected' flag,
        // so the summary is always accurate regardless of any edge-case flag mismatch.
        $selectedgroupname = get_string('allparticipants');
        if ($selectedgroup > 0) {
            foreach ($groupoptions as $groupoption) {
                if ((int)$groupoption['id'] === $selectedgroup) {
                    $selectedgroupname = $groupoption['name'];
                    break;
                }
            }
        }

        $rawgrade = '';
        if ($canask && ((int)$hotquestion->grade !== 0)) {
            $count = (object)[
                'rawgrade' => $hq->calculate_user_ratings($USER->id),
                'max' => $hotquestion->postmaxgrade,
            ];
            $rawgrade = get_string('rawgrade', 'hotquestion', $count);
        }

        $toolbarargs = [
            'cmid' => $cm->id,
            'courseid' => $course->id,
            'groupid' => $selectedgroup,
            'roundid' => !empty($hq->get_currentround()->id) ? (int)$hq->get_currentround()->id : -1,
        ];

        $prevround = $hq->get_prevround();
        $nextround = $hq->get_nextround();

        $js = <<<'JS'
var self = this;

this.textControl = this.FormBuilder.control(this.CONTENT_OTHERDATA.text || '');
this.attachmentsFiles = [];
this.advanced = false;
this.isSaving = false;

this.getCurrentSite = function() {
    if (self.CoreSites && typeof self.CoreSites.getCurrentSite === 'function') {
        return self.CoreSites.getCurrentSite();
    }
    if (self.CoreSitesProvider && typeof self.CoreSitesProvider.getCurrentSite === 'function') {
        return self.CoreSitesProvider.getCurrentSite();
    }
    throw new Error('CoreSites unavailable');
};

this.onAdvancedChanged = function(event) {
    this.advanced = !!(event && event.detail && event.detail.value === 'advanced');
};

this.getAttachmentsDraftItemId = function() {
    var files = this.attachmentsFiles;
    if (!Array.isArray(files)) {
        return 0;
    }

    for (var i = 0; i < files.length; i++) {
        var file = files[i] || {};
        var candidate = parseInt(file.itemid || file.itemId || file.draftitemid || file.draftItemId || 0, 10);
        if (candidate > 0) {
            return candidate;
        }
    }

    return parseInt((this.CONTENT_OTHERDATA && this.CONTENT_OTHERDATA.itemid) || 0, 10);
};

this.getFileUploaderService = function() {
    if (this.CoreFileUploader && typeof this.CoreFileUploader.uploadOrReuploadFiles === 'function') {
        return this.CoreFileUploader;
    }
    if (this.CoreFileUploaderProvider && typeof this.CoreFileUploaderProvider.uploadOrReuploadFiles === 'function') {
        return this.CoreFileUploaderProvider;
    }
    return null;
};

this.uploadAttachmentsAndGetDraftItemId = async function() {
    var files = this.attachmentsFiles;
    if (!Array.isArray(files) || files.length === 0) {
        return this.getAttachmentsDraftItemId();
    }

    var uploader = this.getFileUploaderService();
    if (!uploader) {
        throw new Error('File uploader service unavailable in this app runtime.');
    }

    var cmid = parseInt((this.CONTENT_OTHERDATA && this.CONTENT_OTHERDATA.cmid) || 0, 10);
    var uploadeditemid = await uploader.uploadOrReuploadFiles(files, 'mod_hotquestion', cmid);
    uploadeditemid = parseInt(uploadeditemid || 0, 10);
    if (uploadeditemid > 0) {
        return uploadeditemid;
    }

    return this.getAttachmentsDraftItemId();
};

this.saveQuestion = async function() {
    if (self.isSaving) {
        return;
    }
    self.isSaving = true;

    try {
        var attachmentsitemid = await self.uploadAttachmentsAndGetDraftItemId();
        var contentvalue = self.textControl ? self.textControl.value : '';
        var params = {
            cmid: self.CONTENT_OTHERDATA.cmid,
            content: contentvalue,
            format: 1,
            itemid: attachmentsitemid > 0 ? attachmentsitemid : self.CONTENT_OTHERDATA.itemid,
            anonymous: self.CONTENT_OTHERDATA.anonymous ? 1 : 0,
        };

        var site = await self.getCurrentSite();
        var result = await site.write('mod_hotquestion_submit_question', params, {
            getFromCache: 0,
            saveToCache: 0,
        });

        if (!result || result.status !== 'ok') {
            throw new Error('Question submission failed.');
        }

        if (self.CoreToasts && typeof self.CoreToasts.show === 'function') {
            try {
                self.CoreToasts.show({
                    message: 'core.changessaved',
                    translateMessage: true,
                    cssClass: 'core-toast-success',
                });
            } catch (e) {
                // Ignore toast API differences between app versions.
            }
        }

        await self.refreshContent(false);
    } catch (err) {
        if (self.CoreAlerts && typeof self.CoreAlerts.showError === 'function') {
            self.CoreAlerts.showError(err);
        }
    } finally {
        self.isSaving = false;
    }
};
JS;

        $data = [
            'cmid'         => $cm->id,
            'courseid'     => $course->id,
            'cachekey'     => time(),
            'name'         => format_string($hotquestion->name),
            'intro'        => format_module_intro('hotquestion', $hotquestion, $cm->id),
            'questions'    => $questionitems,
            'hasquestions' => !empty($questionitems),
                'showonlyownnotice' => $showonlyown,
                'showonlyownnoticetext' => $showonlyown
                    ? get_string('minentriesbeforeviewnotice', 'hotquestion', (object)[
                        'required' => $minquestionsview,
                        'current' => $userroundpostcount,
                    ])
                    : '',
            'canask'       => $canask && $isopen,
                'canattachments' => $canask && $isopen,
            'allowanonymous' => !empty($hotquestion->anonymouspost),
            'showgroupselector' => !empty($groupoptions),
            'groupoptions' => $groupoptions,
            'selectedgroupid' => (int)$selectedgroup,
            'selectedgroupname' => $selectedgroupname,
                'showrawgrade' => !empty($rawgrade),
                'rawgrade' => $rawgrade,
                'showviewgrades' => (($canmanageentries || $canrate || $canask) && ((int)$hotquestion->grade !== 0)),
                'viewgradesurl' => (new \moodle_url('/mod/hotquestion/grades.php', ['id' => $cm->id, 'group' => $selectedgroup]))->out(false),
                'shownewround' => ($canrate || $canmanageentries),
                'showremoveround' => $canmanageentries,
                'showprevround' => !empty($prevround),
                'shownextround' => !empty($nextround),
                'currentroundid' => !empty($hq->get_currentround()->id) ? (int)$hq->get_currentround()->id : -1,
                'prevroundid' => !empty($prevround) ? (int)$prevround->id : 0,
                'nextroundid' => !empty($nextround) ? (int)$nextround->id : 0,
                'currentroundx' => (int)$hq->get_currentroundx(),
                'roundcount' => (int)$hq->get_roundcount(),
                'toolbarargsjson' => json_encode($toolbarargs),
                'teacherprioritylabel' => clean_param(format_text($hotquestion->teacherprioritylabel, FORMAT_MOODLE), PARAM_TEXT),
                'heatlabel' => clean_param(format_text($hotquestion->heatlabel, FORMAT_MOODLE), PARAM_TEXT),
                'removelabel' => clean_param(format_text($hotquestion->removelabel, FORMAT_MOODLE), PARAM_TEXT),
                'approvallabel' => clean_param(format_text($hotquestion->approvallabel, FORMAT_MOODLE), PARAM_TEXT),
            'viewurl'      => (new \moodle_url('/mod/hotquestion/view.php', ['id' => $cm->id]))->out(false),
        ];

        return [
            'templates' => [[
                'id'   => 'main',
                'html' => $OUTPUT->render_from_template('mod_hotquestion/mobileapp/mobile_view', $data),
            ]],
            'javascript' => $js,
            'otherdata'  => [
                'cmid' => $cm->id,
                'courseid' => $course->id,
                'itemid' => $draftitemid,
                'anonymous' => 0,
                'text' => '',
                'maxattachments' => 9,
                'maxbytes' => (int)$course->maxbytes,
            ],
        ];
    }

    /**
     * Mobile question submission page — shows the rich-text editor form.
     *
     * @param array $args Incoming app args.
     * @return array
     */
    public static function mobile_submit_question($args) {
        global $DB, $OUTPUT, $USER;

        $cmid = (int)($args['cmid'] ?? 0);
        $cm = get_coursemodule_from_id('hotquestion', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $hotquestion = $DB->get_record('hotquestion', ['id' => $cm->instance], '*', MUST_EXIST);
        $modulecontext = \context_module::instance($cm->id, MUST_EXIST);
        /** @var \context $context */
        $context = \context::instance_by_id($modulecontext->id, MUST_EXIST);

        require_login($course, false, $cm);
        require_capability('mod/hotquestion:view', $context);

        $canask = has_capability('mod/hotquestion:ask', $context);

        // Determine whether the activity is open for new questions.
        $timenow = time();
        $isopen = true;
        if (!empty($hotquestion->timeopen) && $hotquestion->timeopen > $timenow) {
            $isopen = false;
        }
        if (!empty($hotquestion->timeclose) && $hotquestion->timeclose < $timenow) {
            $isopen = false;
        }

        $canedit = $canask && $isopen;

        // Prepare a fresh draft file area for the question's inline media.
        $draftitemid = 0;
        if ($canedit) {
            $draftitemid = file_get_unused_draft_itemid();
        }

        $allowanonymous = !empty($hotquestion->anonymouspost);

        $js = <<<'JS'
var self = this;

this.isSaving = false;

this.getCurrentSite = function() {
    if (self.CoreSites && typeof self.CoreSites.getCurrentSite === 'function') {
        return self.CoreSites.getCurrentSite();
    }
    if (self.CoreSitesProvider && typeof self.CoreSitesProvider.getCurrentSite === 'function') {
        return self.CoreSitesProvider.getCurrentSite();
    }
    throw new Error('CoreSites unavailable');
};

this.saveQuestion = async function() {
    if (self.isSaving) {
        return;
    }
    self.isSaving = true;

    try {
        var params = {
            cmid:      self.CONTENT_OTHERDATA.cmid,
            content:   self.textControl ? self.textControl.value : '',
            format:    1,
            itemid:    self.CONTENT_OTHERDATA.itemid,
            anonymous: self.CONTENT_OTHERDATA.anonymous ? 1 : 0,
        };

        var site = await self.getCurrentSite();
        await site.write('mod_hotquestion_submit_question', params, {
            getFromCache: 0,
            saveToCache: 0,
        });

        if (self.CoreToasts && typeof self.CoreToasts.show === 'function') {
            try {
                self.CoreToasts.show({
                    message: 'core.changessaved',
                    translateMessage: true,
                    cssClass: 'core-toast-success',
                });
            } catch (e) {
                // Ignore toast API differences between app versions.
            }
        }

        history.back();
    } catch (err) {
        if (self.CoreAlerts && typeof self.CoreAlerts.showError === 'function') {
            self.CoreAlerts.showError(err);
        }
    } finally {
        self.isSaving = false;
    }
};
JS;

        $data = [
            'cmid'           => $cm->id,
            'courseid'       => $course->id,
            'name'           => format_string($hotquestion->name),
            'intro'          => format_module_intro('hotquestion', $hotquestion, $cm->id),
            'canedit'        => $canedit,
            'allowanonymous' => $allowanonymous,
        ];

        return [
            'templates' => [[
                'id'   => 'main',
                'html' => $OUTPUT->render_from_template('mod_hotquestion/mobileapp/mobile_submit_question', $data),
            ]],
            'javascript' => $js,
            'otherdata'  => [
                'cmid'      => $cm->id,
                'courseid'  => $course->id,
                'itemid'    => $draftitemid,
                'anonymous' => 0,
            ],
        ];
    }

    private static function mobile_get_inline_attachment_media_type($file) {
        $mimetype = strtolower((string)$file->get_mimetype());
        if (strpos($mimetype, 'video/') === 0) {
            return 'video';
        }
        if (strpos($mimetype, 'audio/') === 0) {
            return 'audio';
        }
        if (strpos($mimetype, 'image/') === 0) {
            return 'image';
        }

        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if (in_array($extension, ['mp4', 'webm', 'ogg', 'ogv', 'm4v', 'mov'], true)) {
            return 'video';
        }
        if (in_array($extension, ['mp3', 'm4a', 'aac', 'wav', 'oga', 'opus'], true)) {
            return 'audio';
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            return 'image';
        }

        return '';
    }

    private static function mobile_get_inline_preview_mimetype($file, $mediatype) {
        $rawmimetype = strtolower(trim((string)$file->get_mimetype()));
        if ($mediatype === 'video' && strpos($rawmimetype, 'video/') === 0) {
            return $rawmimetype;
        }
        if ($mediatype === 'audio' && strpos($rawmimetype, 'audio/') === 0) {
            return $rawmimetype;
        }

        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        $videomap = [
            'mp4' => 'video/mp4',
            'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
        ];
        $audiomap = [
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'wav' => 'audio/wav',
            'oga' => 'audio/ogg',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/opus',
            'webm' => 'audio/webm',
        ];

        if ($mediatype === 'video') {
            return $videomap[$extension] ?? '';
        }
        if ($mediatype === 'audio') {
            return $audiomap[$extension] ?? '';
        }

        return '';
    }

    private static function mobile_render_inline_attachment_preview($mediatype, $filename, $streamurl, $downloadurl, $mimetype) {
        $stream = $streamurl->out(false);
        $fallback = \html_writer::link($downloadurl, get_string('download'));
        $media = '';

        if ($mediatype === 'video') {
            $sourceattrs = ['src' => $stream];
            if ($mimetype !== '') {
                $sourceattrs['type'] = $mimetype;
            }
            $source = \html_writer::empty_tag('source', $sourceattrs);
            $media = \html_writer::tag('video', $source . $fallback, [
                'controls' => 'controls',
                'preload' => 'metadata',
                'class' => 'hotquestion-attachment-video',
            ]);
        } else if ($mediatype === 'audio') {
            $sourceattrs = ['src' => $stream];
            if ($mimetype !== '') {
                $sourceattrs['type'] = $mimetype;
            }
            $source = \html_writer::empty_tag('source', $sourceattrs);
            $media = \html_writer::tag('audio', $source . $fallback, [
                'controls' => 'controls',
                'preload' => 'metadata',
                'class' => 'hotquestion-attachment-audio',
            ]);
        } else if ($mediatype === 'image') {
            $media = \html_writer::empty_tag('img', [
                'src' => $stream,
                'alt' => s($filename),
                'loading' => 'lazy',
                'class' => 'hotquestion-attachment-image',
            ]);
        }

        if ($media === '') {
            return '';
        }

        return \html_writer::tag(
            'div',
            \html_writer::tag('div', s($filename), ['class' => 'hotquestion-attachment-media-name']) . $media,
            ['class' => 'hotquestion-attachment-media-wrap hotquestion-attachment-media-wrap-' . $mediatype]
        );
    }

    private static function mobile_file_is_webm($file) {
        $mimetype = strtolower(trim((string)$file->get_mimetype()));
        if ($mimetype === 'video/webm' || $mimetype === 'audio/webm') {
            return true;
        }

        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        return $extension === 'webm';
    }

    private static function mobile_strip_unplayable_embedded_webm($html) {
        $pattern = '~<video\b[^>]*>.*?\.webm(?:[^<\"\']*)?.*?</video>~is';
        return preg_replace($pattern, '', (string)$html);
    }

    private static function mobile_entry_text_references_file($entrytext, $file) {
        $decodedtext = rawurldecode(html_entity_decode((string)$entrytext, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($decodedtext === '') {
            return false;
        }

        $filepath = trim((string)$file->get_filepath(), '/');
        $filename = (string)$file->get_filename();
        $relativepath = ($filepath === '') ? $filename : ($filepath . '/' . $filename);

        $pluginfiletoken = '@@PLUGINFILE@@/';
        if (
            strpos($decodedtext, $pluginfiletoken . $relativepath) !== false
                || strpos($decodedtext, $pluginfiletoken . $filename) !== false
        ) {
            return true;
        }

        $pluginfilepath = '/mod_hotquestion/question/';
        return strpos($decodedtext, $pluginfilepath . $relativepath) !== false
            || strpos($decodedtext, $pluginfilepath . $filename) !== false;
    }
}
