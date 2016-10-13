/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */

/** 
 * Container for a Repository.
 */
class Repository {

	/**
	 * Constructor
	 * @param id String
	 * @param xmlDef XMLNode
	 */
	constructor(id, xmlDef){
        this.label = '';
		this.id = id;
        this.accessType = '';
        this.nodeProviderDef = undefined;
        this.allowCrossRepositoryCopy= false;
        this.userEditable = false;
        this.slug = '';
        this.owner = '';
        this.description = '';
        this._hasContentFilter = false;
        this._hasUserScope = false;
        this._repositoryType = 'local';
        this._accessStatus = null;
        this._lastConnection = null;
        if(window.ajxpResourcesFolder){
    		this.icon = window.ajxpResourcesFolder+'/images/actions/16/network-wired.png';
        }
		this.resourcesManager = new ResourcesManager();
		if(xmlDef) this.loadFromXml(xmlDef);
	}
	
	/**
	 * @returns String
	 */
	getId(){
		return this.id;
	}

	/**
	 * @returns String
	 */
	getShareId(){
		return this.id.replace(/ocs_remote_share_/, '');

	}
	
	/**
	 * @returns String
	 */
	getLabel(){
		return this.label;
	}
	/**
	 * @param label String
	 */
	setLabel(label){
		this.label = label;
	}

    getHtmlBadge(noClass=false){
        if(!this.label) return '';
        if(!this.badge){
            var className = noClass ? '' : 'letter_badge';
            var letters = this.label.split(" ").map(function(word){return word.substr(0,1)}).slice(0,3).join("");
            this.badge = "<span class='"+className+"'>"+ letters +"</span>";
        }
        return this.badge;
    }

    /**
     * @return String
     */
    getDescription(){
        return this.description;
    }

	/**
	 * @returns String
	 */
	getIcon(){
		return this.icon;
	}
	/**
	 * @param icon String
	 */
	setIcon(icon){
		this.icon = icon;
	}

    /**
     * @return String
     */
    getOwner(){
        return this.owner;
    }

	/**
	 * @returns String
	 */
	getAccessType(){
		return this.accessType;
	}
	/**
	 * @param access String
	 */
	setAccessType(access){
		this.accessType = access;
	}
	
	/**
	 * Triggers ResourcesManager.load
	 */
	loadResources(){
		this.resourcesManager.load(null, true);
	}
	
	/**
	 * @returns Object
	 */
	getNodeProviderDef(){
		return this.nodeProviderDef;
	}
	
	/**
	 * @param slug String
	 */
	setSlug(slug){
		this.slug = slug;
	}
	
	/**
	 * @returns String
	 */
	getSlug(){
		return this.slug;
	}

    getOverlay(){
        return (this.getOwner() ? resolveImageSource("shared.png", "/images/overlays/ICON_SIZE", 8):"");
    }

    /**
     * @returns {boolean}
     */
    hasContentFilter(){
        return this._hasContentFilter;
    }

    /**
     * @returns {boolean}
     */
    hasUserScope(){
        return this._hasUserScope;
    }

    /**
     * @returns {string}
     */
    getRepositoryType(){
        return this._repositoryType;
    }

    /**
     * @returns {string}
     */
    getAccessStatus(){
        return this._accessStatus;
    }

    setAccessStatus(status) {
    	this._accessStatus = status;
    }

    getLastConnection(){
        return this._lastConnection;
    }

	/**
	 * Parses XML Node
	 * @param repoNode XMLNode
	 */
	loadFromXml(repoNode){
		if(repoNode.getAttribute('allowCrossRepositoryCopy') && repoNode.getAttribute('allowCrossRepositoryCopy') == "true"){
			this.allowCrossRepositoryCopy = true;
		}
		if(repoNode.getAttribute('hasContentFilter') && repoNode.getAttribute('hasContentFilter') == "true"){
			this._hasContentFilter = true;
		}
		if(repoNode.getAttribute('userScope') && repoNode.getAttribute('userScope') == "true"){
			this._hasUserScope = true;
		}
		if(repoNode.getAttribute('repository_type')){
			this._repositoryType = repoNode.getAttribute('repository_type');
		}
		if(repoNode.getAttribute('access_status')){
			this._accessStatus = repoNode.getAttribute('access_status');
		}
        if(repoNode.getAttribute('last_connection')){
            this._lastConnection = repoNode.getAttribute('last_connection');
        }

		if(repoNode.getAttribute('user_editable_repository') && repoNode.getAttribute('user_editable_repository') == "true"){
			this.userEditable = true;
		}
		if(repoNode.getAttribute('access_type')){
			this.setAccessType(repoNode.getAttribute('access_type'));
		}
		if(repoNode.getAttribute('repositorySlug')){
			this.setSlug(repoNode.getAttribute('repositorySlug'));
		}
		if(repoNode.getAttribute('owner')){
			this.owner = repoNode.getAttribute('owner');
		}
		for(var i=0;i<repoNode.childNodes.length;i++){
			var childNode = repoNode.childNodes[i];
            if(childNode.nodeName == "label"){
                this.setLabel(childNode.firstChild.nodeValue);
            }else if(childNode.nodeName == "description"){
                this.description = childNode.firstChild.nodeValue;
            }else if(childNode.nodeName == "client_settings"){
                if(childNode.getAttribute('icon_tpl_id')){
                    this.setIcon(window.ajxpServerAccessPath+'&get_action=get_user_template_logo&template_id='+childNode.getAttribute('icon_tpl_id')+'&icon_format=small');
                }else{
                    this.setIcon(childNode.getAttribute('icon'));
                }
                for(var j=0; j<childNode.childNodes.length;j++){
                    var subCh = childNode.childNodes[j];
                    if(subCh.nodeName == 'resources'){
                        this.resourcesManager.loadFromXmlNode(subCh);
                    }else if(subCh.nodeName == 'node_provider'){
                        var nodeProviderName = subCh.getAttribute("ajxpClass");
                        var nodeProviderOptions = JSON.parse(subCh.getAttribute("ajxpOptions"));
                        this.nodeProviderDef = {name:nodeProviderName, options:nodeProviderOptions};
                    }
                }
            }
        }
    }
}
