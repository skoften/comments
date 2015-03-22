<?php

namespace Craft;

class CommentsService extends BaseApplicationComponent
{
    private $_commentsById;
    private $_fetchedAllComments = false;

    public function getCriteria(array $attributes = array())
    {
        return craft()->elements->getCriteria('Comments_Comment', $attributes);
    }

    public function getAllComments()
    {
        $attributes = array('order' => 'dateCreated');

        return $this->getCriteria($attributes)->find();
    }

    public function getCommentById($commentId)
    {
        return $this->getCriteria(array('limit' => 1, 'id' => $commentId))->first();
    }

    public function getCommentModels($criteria = null)
    {
        if (!$this->_fetchedAllComments) {
            $records = Comments_CommentRecord::model()->ordered()->findAll();
            $this->_commentsById = Comments_CommentModel::populateModels($records, 'id');
            $this->_fetchedAllComments = true;
        }

        return array_values($this->_commentsById);
    }

    public function getEntriesWithComments()
    {
        $criteria = new \CDbCriteria();
        $criteria->group = 'entryId';

        $comments = Comments_CommentRecord::model()->findAll($criteria);

        $entries = array();
        foreach ($comments as $comment) {
            $entry = craft()->entries->getEntryById($comment->entryId);
            $entries[] = $entry;
        }

        return $entries;
    }
/*
    public function getCommentById($commentId)
    {
        $record = Comments_CommentRecord::model()->findById($commentId);
        return Comments_CommentModel::populateModel($record);
    }*/

    public function getStructureId()
    {
        return craft()->plugins->getPlugin('comments')->getSettings()->structureId;
    }

    public function saveComment(Comments_CommentModel $comment)
    {
        $isNewComment = !$comment->id;

        // Check for parent Comments
        $hasNewParent = $this->_checkForNewParent($comment);

        if ($hasNewParent) {
            if ($comment->parentId) {
                $parentComment = $this->getCommentById($comment->parentId);

                if (!$parentComment) {
                    throw new Exception(Craft::t('No comment exists with the ID “{id}”.', array('id' => $comment->parentId)));
                }
            } else {
                $parentComment = null;
            }

            $comment->setParent($parentComment);
        }

        // Get the comment record
        if (!$isNewComment) {
            $commentRecord = Comments_CommentRecord::model()->findById($comment->id);

            if (!$commentRecord) {
                throw new Exception(Craft::t('No comment exists with the ID “{id}”.', array('id' => $comment->id)));
            }
        } else {
            $commentRecord = new Comments_CommentRecord();
        }


        // Load in all the attributes from the Comment model into this record
        $commentRecord->setAttributes($comment->getAttributes(), false);


        // Now, lets try to save all this
        if ($comment->validate()) {
            $success = craft()->elements->saveElement($comment);

            if (!$success) {
                return array('error' => $comment->getErrors());
            }

            // Now that we have an element ID, save it on the other stuff
            if ($isNewComment) {
                $commentRecord->id = $comment->id;
            }

            // Save the actual comment
            $commentRecord->save(false);

            // Has the parent changed?
            if ($hasNewParent) {
                if (!$comment->parentId) {
                    craft()->structures->appendToRoot($this->getStructureId(), $comment);
                } else {
                    craft()->structures->append($this->getStructureId(), $comment, $parentComment);
                }
            }

            return $comment;
        } else {
            $comment->addErrors($commentRecord->getErrors());
            return array('error' => $comment->getErrors());
        }
    }











    private function _checkForNewParent(Comments_CommentModel $comment)
    {
        // Is it a brand new comment?
        if (!$comment->id) {
            return true;
        }

        // Was a parentId actually submitted?
        if ($comment->parentId === null) {
            return false;
        }

        // Is it set to the top level now, but it hadn't been before?
        if ($comment->parentId === '' && $comment->level != 1) {
            return true;
        }

        // Is it set to be under a parent now, but didn't have one before?
        if ($comment->parentId !== '' && $comment->level == 1) {
            return true;
        }

        // Is the parentId set to a different comment ID than its previous parent?
        $criteria = craft()->elements->getCriteria('Comments_Comment');
        $criteria->ancestorOf = $comment;
        $criteria->ancestorDist = 1;
        $criteria->status = null;
        $criteria->localeEnabled = null;

        $oldParent = $criteria->first();
        $oldParentId = ($oldParent ? $oldParent->id : '');

        if ($comment->parentId != $oldParentId) {
            return true;
        }

        // Must be set to the same one then
        return false;
    }


}