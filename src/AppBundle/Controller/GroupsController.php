<?php

namespace AppBundle\Controller;

use AppBundle\Document\Context;
use AppBundle\Document\Group;
use AppBundle\Document\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class GroupsController extends BaseController
{

    /**
     * @Route("/groups", name="list_user_groups")
     *
     * @return Response
     */
    public function listGroupsAction()
    {
        /** @var User $user */
        $user = $this->getUser();
        $groups = $user->getGroups();

        return $this->render('@App/Groups/listGroups.html.twig', array(
            'activeMenu' => "groups",
            'groups' => $groups,
        ));
    }

    /**
     * @Route("/group/{id}", name="group")
     *
     * @param $id
     * @return Response
     */
    public function groupAction($id)
    {
        $group = $this->getRepo("AppBundle:Group")->find($id);

        return $this->render('@App/Groups/group.html.twig', array(
            'activeMenu' => "group",
            'group' => $group,
        ));
    }

    /**
     * @Route("/new-group", name="create_new_group")
     * @param Request $request
     * @return Response
     */
    public function createNewGroupAction(Request $request)
    {
        $errors = array();

        if ($request->isMethod("POST")) {
            $em = $this->getManager();

            $postData = $request->request;

            if (!$postData->has("name") || $postData->get("name") == "") {
                $errors["group"] = "The name of the group cannot be empty.";
            }

            $group_name = $postData->get("name");

            $group = $em->getRepository("AppBundle:Group")->findOneBy(array('name' => $group_name));

            if ($group != null) {
                $errors["group"] = "A group with name \"" . $group_name . "\" already exists.";
            }

            if (empty($errors)) {
                $group = new Group();
                $group->setName($postData->get("name"));
                $group->setOwner($this->getUser());
                $group->addUser($this->getUser());

                $em->persist($group);
                $em->flush();

                return $this->redirect($this->generateUrl("list_user_groups"));
            }
        }

        return $this->render('@App/Groups/listGroups.html.twig', array(
            'activeMenu' => "groups",
            'errors' => $errors,
            'groups' => $this->getUser()->getGroups()
        ));
    }

    /**
     * @Route("/share-context/{id}", name="share_context")
     *
     * @param $id
     * @param Request $request
     * @return Response
     */
    public function shareContextToGroupAction($id, Request $request)
    {
        $em = $this->getManager();
        /** @var Context $context */
        $context = $em->getRepository("AppBundle:Context")->find($id);
        $errors = array();

        if ($request->isMethod("POST")) {
            $postData = $request->request;

            if (!$this->isValidContext($context, array("not null", "is own"))) {
                return $this->renderFoundError("my_contexts");
            }

            if (!$postData->has("group")) {
                $errors['group'] = "Group not found";
            }

            $groupId = $postData->get("group");
            /** @var Group $group */
            $group = $em->getRepository("AppBundle:Group")->find($groupId);

            if ($group == null) {
                $errors['group'] = "Group" . $groupId . " not found";
            } else {
                if (empty($errors)) {
                    if ($group->hasContext($context)) {
                        $group->removeContext($context);
                    } else {
                        $group->addContext($context);
                    }

                    $em->persist($group);
                    $em->flush();

                    return $this->redirect($this->generateUrl("view_context", array(
                        "id" => $context->getId(),
                    )));
                }
            }
        }

        return $this->render("@App/Context/context.html.twig", array(
            'context' => $context,
            'activeMenu' => "contexts",
            'errors' => $errors,
        ));
    }

    /**
     * @Route("/add-member", name="add_member")
     * @param $request
     * @return Response
     * @internal param Request $request
     */
    public function addMemberToGroupAction(Request $request)
    {
        $errors = array();
        $requester = $this->getUser();
        if ($request->isMethod("POST")) {
            $em = $this->getManager();

            $postData = $request->request;

            if (!$postData->has("username") || $postData->get("username") == "") {
                $errors["username"] = "The name of the user cannot be empty.";
            }

            if (!$postData->has("group-id") || $postData->get("group-id") == "") {
                $errors["group"] = "The id of the group cannot be empty.";
            }

            /** @var Group $group */
            $group = $em->getRepository("AppBundle:Group")->find($postData->get("group-id"));

            if ($group == null) {
                $errors["group"] = "The group doesn't exist.";
            } else {
                if (!$group->hasUser($requester)) {
                    $errors["group"] = "The user can't add member to this group.";
                }
            }

            $userName = $postData->get("username");
            $user = $em->getRepository("AppBundle:User")->findOneBy(array('username' => $userName));

            if ($user == null) {
                $errors["username"] = "Username \"" . $userName . "\" does not exist.";
            } else {
                if ($group->hasUser($user)) {
                    $errors["group"] = "User \"" . $userName . "\" is already registered to the group \"" . $group->getName() . "\"";
                }
            }
            if (empty($errors)) {
                $group->addUser($user);
                $em->persist($group);
                $em->flush();

                return $this->redirect($this->generateUrl("list_user_groups"));
            }
        }


        /** @var User $user */
        $user = $this->getUser();
        $groups = $user->getGroups();
        return $this->render('@App/Groups/listGroups.html.twig', array(
            'activeMenu' => "groups",
            'errors' => $errors,
            'groups' => $groups
        ));
    }


    /**
     * @Route("/delete-group/{id}", name="delete_group")
     *
     * @param $id
     * @return Response
     */
    public function deleteGroupAction($id)
    {
        $em = $this->getManager();
        /** @var User $user */
        $user = $this->getUser();
        /** @var Group $group */
        $group = $em->getRepository("AppBundle:Group")->find($id);

        $groups = $user->getGroups();

        if ($group == null) {
            $errors['group'] = "Group not found.";
        }

        if ($group->getOwner() != $user) {
            $errors['group'] = "Not authorised.";
        }

        if (empty($errors)) {
            $em->remove($group);
            $em->flush();
            return $this->redirect($this->generateUrl("list_user_groups"));
        }

        return $this->render('@App/Groups/listGroups.html.twig', array(
            'activeMenu' => "groups",
            'errors' => $errors,
            'groups' => $groups
        ));
    }

}
