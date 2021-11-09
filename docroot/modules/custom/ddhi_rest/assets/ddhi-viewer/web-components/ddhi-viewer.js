/**
 *  Prototype for the DDHI Viewer Web Application
 *
 *  The DDHI Viewer is a series of visualizations that display data retrieved from a
 *  DDHI Oral History Repository. It is intended to be modular, allowing you to build
 *  visualizations and information displays as individual Web Components.
 *
 */
 

/**
 *  User-facing custom elements.
 *  These elements will allow users to define the parameters of their DDHI Viewer.
 */

/**
 *  DDHIViewHelper.
 *  A plugin that provides presentation tools for DDHI Web Components.
 *  It must be passed the viewer object.
 */
 

class DDHIViewHelper {
  constructor(viewer) {
    this.viewer = viewer;
    this.heartbeat = 800/5; // Used for animations
  }  
  // @method connectedCallback()
  // @description Initializer method for this component.

  
  connectedCallback() {
  }
  
  // Font canÕt be loaded directly into the ShadowDOM, they can
  // only be inherited from the page itself.
  // This can be called from any object via a Viewhelper instance.
  
  loadDDHIFonts() {
        
    if(document.querySelector('head') !== null) {
      var headElement = document.querySelector('head');
      
      if (headElement.querySelector("[title='DDHI Viewer Fonts']") === null) {
        var link = document.createElement('link');
        link.setAttribute('title','DDHI Viewer Fonts');
        link.setAttribute('rel','stylesheet');
        link.setAttribute('href','https://fonts.googleapis.com/css?family=Roboto|Aleo');
        headElement.appendChild(link);
      }
    }
    
  }  
  
  fadeOut(fadeTarget,display='grid') {
    var fadeEffect = setInterval(function () {
        if (!fadeTarget.style.opacity) {
            fadeTarget.style.opacity = 1;
        }
        if (fadeTarget.style.opacity > 0) {
            fadeTarget.style.opacity -= 0.1;
        } else {
            clearInterval(fadeEffect);
            fadeTarget.style.display = 'none';
        }
    }, 200);
  }
    
  fadeIn(fadeTarget,display='grid') {
    fadeTarget.style.opacity = 0;
    fadeTarget.style.display = display;
    
    var fadeEffect = setInterval(function () {
        if (!fadeTarget.style.opacity) {
            fadeTarget.style.opacity = 0;
        }
        if (fadeTarget.style.opacity < 1) {
            fadeTarget.style.opacity += 0.1;
        } else {
            clearInterval(fadeEffect);
        }
    }, 200);
  }
  
} 


/**
 *  DDHIDataComponent.
 *  The base  DDHI Data Component manages data access for visualizations and information \
 *  panels.
 */
 

class DDHIDataComponent extends HTMLElement {
  constructor() {
    super(); 
    this.repositoryURI; // Set from the ddhi-viewer repository attribute
    this.apiURI; // Derived from above
    this.cdnAssetPath = 'modules/custom/ddhi_rest/assets/ddhi-viewer'; // Derived from above
    this.viewer; // The active viewer
    this.viewHelper; // An instance of the DDHI View Plugin
    this.loading = false;
    this.availableIds = []; // Available data ids for this visualization
    this.activeIds = []; // A list of active data ids
    this.items = {};  // Data keyed by ID.
    this.tempResult; // Holding property for asynchronous data retrieval.
    this.supportedEntityTypes = ['events','persons','places']; // Currently supported entities types.
    this.mentionedEntities = {}; // The list of entities mentioned in a transcript.
    this.wikidataAPIUrl = 'https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&languages=en&sitefilter=enwiki'; 
    this.eventData;
    this.eventDateIndex; // Indexes event dates by id.
  }
  
  // @method connectedCallback()
  // @description Initializer method for this component.

  
  connectedCallback() {
  
    this.loadViewerObject();
    
    
    if (typeof this.viewer !== 'undefined' && this.viewer !== 'null') {
      this.repositoryURI = this.viewer.getAttribute('repository');
      this.apiURI = this.repositoryURI + '/ddhi-api';
      this.viewHelper = new DDHIViewHelper(this.viewer);
    }
  }
  
  // @method getAPIResource()
  // @description A general purpose utility for retrieving data from the repository.
  // @param resource The REST endpoint to retrieve data from, as path from REST URI
  // @param prop The property to populate with results.
  // @param format The format of the response. Supports xml and the default json.
    
  async getAPIResource(resource,prop,format='json') {
        
    const response = await fetch(this.apiURI + '/' + resource + '?_format=' + format, {mode: 'cors'});
    const result = await response.json();
             
    if (!response.ok) {
      const message = `An error has occured: ${response.status}`;
      throw new Error(message);
    }
    
    this[prop] = result;
    
    return response; 
    
  }
  
  // @method getWikiData()
  // @description A general purpose utility for retrieving data from the Wikidata API.
  //   see https://www.wikidata.org/w/api.php?action=help&modules=wbgetentities
  // @param qids An array of qids to submit
  // @param props The properties to retrieve. Defaults to 'sitelinks/urls'.
  //
  // @return The result object
  // NOTE: The maximum number of qids that can be retrieved in one query is 50.
  
  async getWikiData(qids=[],props=['sitelinks/urls','claims']) {
    
    if (qids.length > 50) {
      console.log('Maximum number of Wikidata ids exceeded.');
    }
      
    // Note &origin=* parameter required for MediaWiki/Wikidata requests
               
    const response = await fetch(this.wikidataAPIUrl + '&origin=*' + '&props=' + props.join('|') + '&ids=' + qids.join('|'), {mode: 'cors'});
        
    //const response = await fetch(this.wikidataAPIUrl + '&props=' + props.join('|') + '&ids=' + qids.join('|'));
    const result = await response.json();
    
             
    if (!response.ok) {
      const message = `An error has occured: ${response.status}`;
      throw new Error(message);
    }
                
    return result;
    
  }
  
  /**
   *  @function getEventData()
   *  @description Retrives event data for all event entities from WikiData. 
   *    Date data populates this.eventDateIndex property.  
   *    this.eventDateIndex is keyed by QID, each an object with five properties. Each can be null if empty:
   *      startDate: The claimed start date. (Wikidata Property P580)
   *      endDate: The claimed end date. (Wikidata Property P582)
   *      pointInTime: The date of event if not a range (Wikidate Property P585)
   *      sortDateStart: Merging of startDate and pointInTime for sorting.
   *      sortDateEnd:  Merging of endDate and pointInTime for sorting.
   */
  
  async getEventData() {
    
    this.eventDateIndex = {};
    
    var response = await this.getAssociatedEntitiesByType(this,'eventData',this.getActiveIdFromAttribute(),'events'); 
    var qids = [];
    for (var i=0;i<this.eventData.length;i++) {
      if (typeof this.eventData[i] !== "undefined" && this.eventData[i].qid)
      qids.push(this.eventData[i].qid);
    }
            
    if (qids.length > 0) {
      var wikiDataEvents = await this.getWikiData(qids);
      
              
     
      for (var qid in wikiDataEvents.entities) {
        var claims = wikiDataEvents.entities[qid].claims; // Information claims from Wikidata... in other words the metadata
        
        this.eventDateIndex[qid] = {
          startDate: claims.hasOwnProperty('P580') ? claims.P580[0].mainsnak.datavalue.value.time : null,
          endDate: claims.hasOwnProperty('P582') ? claims.P582[0].mainsnak.datavalue.value.time : null,
          pointInTime: claims.hasOwnProperty('P585') ? claims.P585[0].mainsnak.datavalue.value.time: null,
        }
        
        this.eventDateIndex[qid].sortDateStart = this.eventDateIndex[qid].startDate ? this.eventDateIndex[qid].startDate : this.eventDateIndex[qid].pointInTime;
        this.eventDateIndex[qid].sortDateEnd = this.eventDateIndex[qid].endDate ? this.eventDateIndex[qid].endDate : this.eventDateIndex[qid].pointInTime;
      }
    }
            
    return response;
  }
  
  
  
  // @method loadViewerObject()
  // @description A hack. The entity web component is returning null when inserted
  //  programatically. So a method exists to inject the viewer object externally
  //  and skip tracing it through the DOM.
  // @todo Fix this. It's likely a logic error somewhere.

  
  loadViewerObject(rebuild=false) {
    if (typeof this.viewer == 'undefined') {
      this.viewer = this.closestElement('ddhi-viewer'); // can be null
    }
  }
  
  injectViewerObject(viewer) {
    this.viewer = viewer;
    this.viewHelper = new DDHIViewHelper(this.viewer);
  }
  
  propagateSelectedEntity(id) {
    this.propagateAttributes('selected-entity',id);
  }
  
  // @method propagateAttributes()
  // @description Propagates an attribute to the root elements of all
  //   visualizations and panel components. This is the core of the communication
  //   system between panels, as it allows a component to trigger another component's
  //   attributeChanged function and supply a value for local handling.
  //   
  
  propagateAttributes(attr,value) {
  
    // Propagate to all elements in the visualizations block
    
    if (this.viewer.visualizations.length > 0) {
      this.viewer.visualizations.forEach(function(element){
        element.setAttribute(attr,value);

      })
    }
    
    // Propagate to all elements in the Information block
    
    if (this.viewer.infoPanels.length > 0) {
      this.viewer.infoPanels.forEach(function(element){
        element.setAttribute(attr,value);
      })
    }
    
    // Propagate to all elements marked with a ñpropagateî attribute
    
    this.viewer.shadowRoot.querySelectorAll('[propagate]').forEach(function(element){
        element.setAttribute(attr,value);
      });
      
    // Propagate to the viewer itself
    
    this.viewer.setAttribute(attr,value);
  }
  
  // @method removePropagatedAttributes()
  // @description Removes an attribute from all propagated elements
  
  removePropagatedAttributes(attr) {
  
    // Propagate to all elements in the visualizations block
    
    if (this.visualizations.length > 0) {
      this.visualizations.forEach(function(element){
        element.removeAttribute(attr);

      })
    }
    
    // Propagate to all elements in the Information block
    
    if (this.infoPanels.length > 0) {
      this.infoPanels.forEach(function(element){
        element.removeAttribute(attr);
      })
    }
    
    // Propagate to all elements marked with a ñpropagateî attribute
    
    this.shadowRoot.querySelectorAll('[propagate]').forEach(function(element){
        element.removeAttribute(attr);
      });
      
    // Propagate to the viewer itself
    
    this.removeAttribute(attr);
  }  
  
  // @method getTranscripts()
  // @description Retrieves transcripts from the repository.

  
  async getTranscripts() {
    return this.getAPIResource('collections/transcripts','availableIds');
  }
  
  // @method getItemDataById()
  // @description Fetches the data for a particular item id (e.g. a transcript) and
  //   populates the "items" property. Active Ids are set elsewhere and are stored as
  //   attributes in the component's host element. Logic exists to support multiple
  //   active items if that becomes part of a future specification.
    
  async getItemDataById() {
    var component = this;
    
    this.itemsDataReset();
        
    var activeId = this.getActiveIdFromAttribute();
        
    if (activeId !== null) {
      component.tempResult = null;
      var response = await component.getAPIResource('items/' + activeId,'tempResult');
      this.itemsDataSetItem(activeId,component.tempResult);
      component.tempResult = null;
    }
            
    return response;
  }
  
  // @method getAssociatedEntitiesByType()
  // @description Retrieves all Entities associated with an entry and filtered by entity
  //   type. For instance, this can be used to retrieve all places mentioned in a transcript.
  // @param storeObject An object to assign the value
  // @param property The property of that object to assign the value (property name as string)
  // @param id The id of the entity
  // @param type The type of entity to cross reference. Accepts 
  //   events|locations|people|places|transcripts
  
  async getAssociatedEntitiesByType(storeObject,property,id=null,type='people') {
    var component = this;
    
    if(id==null) {
      var id = this.getActiveIdFromAttribute();
    }
    
    component.tempResult = null;
    var response = await component.getAPIResource("items/" + id + "/" + type,'tempResult');
    storeObject[property] = component.tempResult; // assign by reference
    
    return response;
      
  }
  
  // @method itemsDataReset()
  // @description Resets the object's active items data property. This property
  // stores the full item object (i.e. a transcript)
  
  itemsDataReset() {
    this.items = {};
  }
  
  // @method itemsDataSetItem()
  // @description Sets the object's active item data property. This property
  //   stores the full item object (i.e. a transcript) keyed by id. Note that
  //   it does not retrieve remote data, it's just a setter.
  
  itemsDataSetItem(id,data) {
    this.items[id] = data;
  }
  
  // @method getItemData()
  // @description Returns a single item from the itemData property.
  
  getItemData() {
    var item = {};
        
    for (const prop in this.items) {
      item = this.items[prop];
    }
    
    return item;
  }

      
  // @method getActiveIdFromAttribute()
  // @description Retrieves the current active ID from the componentÍs ddhi-active-id  attribute.
  // @return A single active ID. Null if no ID is present.
  
  getActiveIdFromAttribute() {
    return this.getAttribute('ddhi-active-id');
    }
  
  // @method setData()
  // @description Attach arbitrary data to this element.
  
  setData(prop,data) {
    this[prop] = data;
  }
  
  // @method closestElement()
  // @description Handy utility function courtesy of 
  //   https://stackoverflow.com/questions/54520554/custom-element-getrootnode-closest-function-crossing-multiple-parent-shadowd
  
  closestElement(selector, base = this) {
      function __closestFrom(el) {
          if (!el || el === document || el === window) return null;
          let found = el.closest(selector);
          if (found)
            return found;
          else
            __closestFrom(el.getRootNode().host);
      }

      return __closestFrom(base);
  }
  
  // @method getMentionedEntities()
  // @description Retrieves the entities mentioned in an item.
  //  cross references them with actual mentions in the transcript to get
  //  ordinal information. The result is a flat set of entity objects.
  
  getMentionedEntities(item=null,setProperty=true) {
    var component = this;
  
    if (item==null) {
      item = this.getItemData();
    }
    
    var mentionedEntities = {};
    
    this.supportedEntityTypes.forEach(function(e,i){
      if (item.hasOwnProperty(e)) {
        item[e].forEach(function(entity) {
          mentionedEntities[entity.id] = entity; 
        }); 
      }
    });
    
    if (setProperty==true) {
      this.mentionedEntities = mentionedEntities;
    }
            
    return mentionedEntities;   
  }
  
  // @method getEntitiesByOrderOfMention()
  // @description Returns an array of entity ids in the order that they appear in the
  //  transcript. Entity details can then retrieved from the mentionedEntities property.
  
  getEntitiesByOrderOfMention(item=null) {
    if (item==null) {
      item = this.getItemData();
    }
    
    var orderedEntities = [];
    
    // Thank you https://davidwalsh.name/convert-html-stings-dom-nodes !
    
    let transcript = document.createRange().createContextualFragment(item.transcript);
    
    transcript.querySelectorAll('span').forEach(function (e){
      if (e.hasAttribute('data-entity-id')) {
        orderedEntities.push(e.getAttribute('data-entity-id'));
      
      }
    });
        
    return orderedEntities;
  }
  
  
  
  // @method renderValue()
  // @description View Helper that empties a target element of text and populates
  //   it with a new value.
  // @param element  The target element
  // @param value The replacement value
  
  
  renderValue(element,value) {
    // Check that element exists.
    if (typeof element == 'undefined') {
      return;
    }
    element.textContent = "";
    var wrapper = document.createElement('div');
    wrapper.innerHTML = value;
    element.appendChild(wrapper.firstChild);
  }
}



/**
 *  DDHIVisualization.
 *  A base class for visualizations.
 */

class DDHIVisualization extends DDHIDataComponent {
  constructor() {
    super(); 
  }
  
  // @method connectedCallback()
  // @description Initializer method for this component.
  
  connectedCallback() {
    super.connectedCallback();
  }
  
}

/**
 *  DDHIVisualization.
 *  A base class for information panels.
 */


class DDHIInfoPanel extends DDHIDataComponent {
  constructor() {
    super(); 
  }
  
  // @method connectedCallback()
  // @description Initializer method for this component.
  
  connectedCallback() {
    super.connectedCallback();
    this.viewHelper.loadDDHIFonts();
  }
  
}



/**
 *  ddhi-entity-browser element.
 *  Basic visualization for the entity browser. Will also serve as a model for other
 *  visualizations
 */
 
customElements.define('ddhi-entity-browser', class extends DDHIVisualization {
  constructor() {
    super();
    this.resetIndices();

    // Attach a shadow root to <ddhi-entity-browser>.
    const shadowRoot = this.attachShadow({mode: 'open'});
    shadowRoot.innerHTML = `
      <style>
        :host {
          overflow: hidden;
          height: 100%;
        }
        
        * {
          transition: opacity 0.2s;
        }
                
        .visualization {
          display: flex;
          flex-direction: column;
          justify-content: space-between;
          height: 100%;
          overflow: auto;

        }
        
        .controls, .labels {
          height: 5rem;
          padding-bottom: var(--ddhi-viewer-padding, 1rem)
        }
        
        .controls {
          display: flex;
          flex-direction: row;
          justify-content: flex-start 
        }
        
        .controls > * {
          margin-right: var(--ddhi-viewer-padding, 1rem)  
        }
        
        .entity-grid {
          flex-shrink: 1;
          flex-grow: 1;
          display: flex;
          flex-direction: row;
          justify-content: flex-start;
          align-items: flex-start;
          flex-wrap: wrap;
          overflow-y: scroll;
          height: 100%;
        }
        
        .devnote {
          font-size: 0.75rem;
          color: #99A2A3;
        }
        
        metadata-field {
          display: inline-block;
          margin-right: 1rem;
        }
        
        .metadata-field .label {
          text-transform: uppercase;
          font-size: 0.75rem;
          color: #919293;
          font-weight: 800;
          display: inline-block;
          margin-right: 0.25rem;
        }
        
        .metadata-field .value {
          font-size: 0.75rem;
          color: #4F5152;
        }
        
        .formlabel {
          color: #99A2A3;
          font-size: 0.75rem;
        }
        
        select {
          -webkit-appearance: none;
          -webkit-border-radius: 0;
          border-width: 0 0 2px 0;
          border-bottom-color: #9BC8EB;
          height: 2rem;
          width: 15rem;
          font-weight: 800;
          font-size: 0.75rem;
          padding-left: 0
        }
        
        option {
           font-size: 0.75rem;
        }
        
      </style>
      <div class='visualization' data-name='DDHI Entity Browser'>
        <div class='controls'>
          <div id='filter-entities'>
            <select>
              <option value='all'>All entity types</option>
              <option value='event'>Event</option>
              <option value='person'>Persons</option>
              <option value='place'>Places</option>
            </select>
            <div class='formlabel'>Display type of entity</div>
          </div>
          <div id='sort-entities'>
            <select>
              <option value='data-appearance'>Appearance</option>
              <option value='data-mention'>Frequency</option>
              <option value='data-title'>Alphabetically</option>
            </select>
            <div class='formlabel'>Sort entities</div>
          </div>
        </div>
        <!--<div class='labels'><span class='devnote'>Entity descriptions to come.</span></div>-->
        <div class='entity-grid'></div>
      </div>
    `;
  }
  
  // @method connectedCallback()
  // @description Initializer method for this component.
  
  connectedCallback() {
    super.connectedCallback();
    this.initFilters();
    this.initSort();
  }

  
  // @method observedAttributes()
  // @description Lists the attributes to monitor. Listed attributes will
  //   trigger the attributeChangedCallback when their values change.
  // @return An array of monitored attributes.
  
  static get observedAttributes() {
    return ['ddhi-active-id','selected-entity','entity-sort','entity-filter'];
  }

  // @method attributeChangedCallback()
  // @description HTMLElement listener that detects changes to attributes. If the active 
  //   ids are changed it triggers a transcript load process.
  
  /*
   *  A NOTE ON BUILD PROCESS
   *  - Entities are retrieved from the repo when the active id changes.
   *  - The indexEntities() method creates entity-card objects for each entity and adds them to a general index.
   *  - IndexEntities() also adds entity ids to sorted indices for retrieval during rendering
   *  - The render() process checks the value of the sort and filter controls, retrieves the values from the selected sort index, and renders.
   */
  
  async attributeChangedCallback(attrName, oldVal, newVal) {    
    if(attrName == 'ddhi-active-id') {
      await this.getItemDataById();
      this.getMentionedEntities();
      await this.getEventData();
      this.indexEntities();
      this.render();
    }
        
    if (attrName == 'entity-filter') {
      this.filterEntities();
    }
    
    if (attrName == 'entity-sort') {
      this.render();
    }
  }
  
   
  initFilters() {
    const filterElement = this.shadowRoot.querySelector('#filter-entities select');
    var _this = this;
    
    _this.setAttribute('entity-filter','all');
    
    filterElement.addEventListener('change', event => {
        var element = event.currentTarget;
        _this.setAttribute('entity-filter',event.target.value);
    });

  }
  
  initSort() {
    const sortElement = this.shadowRoot.querySelector('#sort-entities select');
    var _this = this;
    
    _this.setAttribute('entity-sort','appearance');
    
    sortElement.addEventListener('change', event => {
        var element = event.currentTarget;
        _this.setAttribute('entity-sort',event.target.value);
    });

  }
  
  
  filterEntities() {
    const grid = this.shadowRoot.querySelector('.entity-grid');
    const entities = this.shadowRoot.querySelectorAll('entity-card');
    
    const filterValue = this.getAttribute('entity-filter');
    
    grid.style.opacity = 0;
    
    window.setTimeout(function() { grid.style.display = 'none' }, this.heartbeat);

            
    entities.forEach(function(entity,i) {
      
      if (filterValue == 'all') {
        entity.style.display = 'block';
      } else {
        
        if (entity.getAttribute('data-entity-type') == filterValue) {
          entity.style.display = 'block';
        } else {
          entity.style.display = 'none';
        }
      }
    });
    
    window.setTimeout(function() { grid.style.display = 'flex'; grid.style.opacity = 1 }, this.heartbeat * 2)
  }
  
  render() {
        
    const grid = this.shadowRoot.querySelector('.entity-grid');
    const entities = this.shadowRoot.querySelectorAll('entity-card');
    const sortValue = this.getAttribute('entity-sort');
    
    if (typeof this.sortIndex[sortValue] == 'undefined') {
      return;
    }
    
    grid.style.opacity = 0;
    
    window.setTimeout(function() { grid.style.display = 'none' }, this.heartbeat);
    
    // Empty grid
    while (grid.firstChild) {
      grid.removeChild(grid.firstChild);
    }    
    
    for (var i=0;i < this.sortIndex[sortValue].length;i++) {
      var id = this.sortIndex[sortValue][i].id;
      grid.appendChild(this.entityCardIndex[id]);
    }
    
    this.filterEntities();
    
    grid.style.opacity = 1;
    
    window.setTimeout(function() { grid.style.display = 'flex'; grid.style.opacity = 1 }, this.heartbeat * 2)
  }
    
  indexEntities() {
    this.resetIndices();
    var _this = this;
    var item = this.getItemData();
    var entityGrid = this.shadowRoot.querySelector('.entity-grid');
    
    entityGrid.textContent = '';
            
    // count appearances of a specific entity
    var entityMention = {};
    
    // count order of appearance
    
    var i = 1;
    
    // Iterate over appearances by order of mention
    
    this.getEntitiesByOrderOfMention().forEach(function(id,i) {
      if (typeof _this.mentionedEntities[id] == 'undefined') {
        return;
      }
      
      var entity = _this.mentionedEntities[id];
      
      if (entityMention.hasOwnProperty(entity.id)) {
        entityMention[entity.id] ++;
      } else {
        entityMention[entity.id] = 1; // first appearance
      }
      
      
      // Create a new entity card, set attributes, and attach the entity data
    
      var entity = _this.mentionedEntities[id];
      var entityCard = document.createElement('entity-card');
        entityCard.setAttribute('data-title',entity.title);
        entityCard.setAttribute('data-entity-id',entity.id);
        entityCard.setAttribute('data-entity-type',entity.resource_type);
        entityCard.setAttribute('data-mention',entityMention[entity.id]);
        entityCard.setAttribute('data-appearance',i);
        entityCard.setData('entity',entity);
        entityCard.injectViewerObject(_this.viewer);
        
        // Add date information as attributes
        
        
        if (entity.resource_type === 'event' && _this.eventDateIndex.hasOwnProperty(entity.id)) {
          entityCard.setAttribute('data-start-date',_this.eventDateIndex[entity.id].startDate);
          entityCard.setAttribute('data-end-date',_this.eventDateIndex[entity.id].endDate);
          entityCard.setAttribute('data-point-in-time',_this.eventDateIndex[entity.id].pointInTime);
          entityCard.setAttribute('data-end-date',_this.eventDateIndex[entity.id].endDate);
          entityCard.setAttribute('data-sort-date-start',_this.eventDateIndex[entity.id].sortDateStart);
          entityCard.setAttribute('data-sort-date-end',_this.eventDateIndex[entity.id].sortDateEnd);
        }
        
        i++; 
      
      var label = document.createElement('div');
        label.setAttribute('slot','label');
        
        var labelstr = entity.title;
        labelstr = labelstr.length > 35 ? labelstr.substring(0,30) + '...' : labelstr;
        label.appendChild(document.createTextNode(labelstr));
        
      var iconlabel = document.createElement('div');
        iconlabel.setAttribute('slot','iconlabel');
        iconlabel.appendChild(document.createTextNode(entityMention[entity.id]));
      
      var heading = document.createElement('h3');
        heading.appendChild(document.createTextNode(entity.title));
      
      var description = document.createElement('description');
      
      
      var contents = document.createElement('div');
        contents.setAttribute('slot','contents');
        contents.appendChild(heading);
        contents.appendChild(description);
        
      entityCard.appendChild(iconlabel);
      entityCard.appendChild(label);
      entityCard.appendChild(contents);
      
      _this.indexEntityByAttribute('data-title',entityCard); // Index cards based on attributes
      _this.indexEntityByAttribute('data-appearance',entityCard,false,4);
      _this.indexEntityByFrequency(entityCard);

      _this.entityCardIndex[entity.id] = entityCard;  // Add card to general index for lookup
      
      entityGrid.appendChild(entityCard);  // Add card to grid
      
    });
    
    this.sortIndices();
    
  }
  
  resetIndices() {
    this.sortIndex = {};
    this.entityCardIndex = {};
  }
  
  /**
   *  Generates sorted indices from entity-card DOM elements.
   *  Elements are added individually.
   *
   *  @param attr   The attribute
   */
  
  indexEntityByAttribute(attr,entity,reduce=true,padNumeric=0) {
        
    if (typeof this.sortIndex[attr] === "undefined") {
      this.sortIndex[attr] = [];
    }
    
    // Padding can help sort numbers properly.
    
    var key = padNumeric == 0 ? entity.getAttribute(attr) : String(entity.getAttribute(attr)).padStart(padNumeric,'0');
    
    var prop = {
      key: key,
      id: entity.getAttribute('data-entity-id')
    };
        
    function uniqueKey(a) {
      var seen = {};
      var out = [];
      var len = a.length;
      var j = 0;
      for(var i = 0; i < len; i++) {
        var key = a[i].key;
        if(seen[key] !== 1) {
          seen[key] = 1;
          out[j++] = a[i];
         }
      }
      return out;      
    }
          
    this.sortIndex[attr].push(prop);
    
    if (reduce === true) {
      this.sortIndex[attr] = uniqueKey(this.sortIndex[attr]);
    } 
  }
  
  indexEntityByFrequency(entity) {
    
    if (typeof this.sortIndex['data-mention'] == 'undefined') {
      this.sortIndex['data-mention'] = [];
    }
    
    var prop = {
      key: parseInt(entity.getAttribute('data-mention')), // key is the frequency of mentions
      id: entity.getAttribute('data-entity-id') // id is the id of the entity
    };
    
    // find the highest number of mentions
    
    function mostFrequentIndex(a) {
      var seen = {};
      var out = [];
      var len = a.length;
      for(var i = 0; i < len; i++) {
        var mcount = a[i].key; // mention count
        var id = a[i].id;
        if(typeof seen[id] === 'undefined' || mcount > seen[id]) {
          seen[id] = mcount; // capture the most frequent mention
        }
      }
      
      var j=0;
      for(var k = 0; k < len; k++) {
        var id = a[k].id;
        var key = a[k].key;
        if(seen[id] === key) { // if the highest number of mentions (seen) is the current entity mention count, output
          out[j++] = a[k];
         }
      }
      
      return out;      
    }
    
    this.sortIndex['data-mention'].push(prop);
    
    this.sortIndex['data-mention'] = mostFrequentIndex(this.sortIndex['data-mention']);
    
  }
  
  sortIndices() {
    
    function compare( a, b ) {      
      if ( a.key < b.key ){
        return -1;
      }
      if ( a.key > b.key ){
        return 1;
      }
      return 0;
    }
    
    function reverseCompare( a, b ) {      
      if ( a.key < b.key ){
        return 1;
      }
      if ( a.key > b.key ){
        return -1;
      }
      return 0;
    }
        
    for(const key in this.sortIndex) {      
      this.sortIndex[key].sort(key=='data-mention' ? reverseCompare : compare);
    }
    
  }
  
});
/**
 *  transcript-html element.
 *  Presents an interview transcript with named entity anchors.
 */
 
customElements.define('wikidata-viewer', class extends DDHIInfoPanel {
  constructor() {
    super(); 
    this.selectedEntity;
    this.selectedEntityElements = [];
    this.previousSelectedEntity = null; // Used to detect a change in selected entities.
    this.wikipediaAPIUrl = 'https://en.wikipedia.org/w/api.php?action=parse&prop=text&formatversion=2&format=json';
    this.wikiData = {};
    this.wikipediaData = {};
    // Attach a shadow root to <transcript-html>.
    const shadowRoot = this.attachShadow({mode: 'open'});
    shadowRoot.innerHTML = `
      <style>
      
        :root {
          --black: #232526  
        }
            
        * {
          color: var(--black);
          font-size: 0.8rem; 
        }
                
        :host {
          display: block;
          height: 100%;
          width: 100%;
        }
        
        
        #info {
          width: 100%;
          height: 100%;
          overflow-y: scroll;
          padding-top: var(--ddhi-viewer-padding,1rem);
        }
        
        h2 {
          font-size: 1.2rem; 
        }
        
        a {
          color: var(--black);
        }
        
        a:hover {
          color: #9D162E; 
        }

        
      </style>
      <div id='info'>
        <h2>Wikipedia Viewer</h2>
        <p class='message'>Select an entity for viewing.</p>
      </div>
    `;
  }
  
  // @method connectedCallback()
  // @description Initializer method for this component.
  
  connectedCallback() {
    var _this = this;
    super.connectedCallback();
  }
    
  // @method observedAttributes()
  // @description Lists the attributes to monitor. Listed attributes will
  //   trigger the attributeChangedCallback when their values change.
  // @return An array of monitored attributes.
  
  static get observedAttributes() {
    return ['ddhi-active-id','selected-entity'];
  }
  
  async attributeChangedCallback(attrName, oldVal, newVal) {    
    if(attrName == 'ddhi-active-id') {
      await this.getItemDataById();
    }    
    
    if(attrName == 'selected-entity') {
      if(newVal.indexOf('Q') === 0) {
        await this.getWikipediaData();
        this.render();
      } else {
        this.renderMessage("No Wikidata information is provided for this item");
      }
    }    
  }
    
  async getWikipediaData() {
    
    //var requestHeaders = new Headers();
    // requestHeaders.append('Origin', window.location.hostname);

    const qid = this.getAttribute('selected-entity');
    
    this.wikiData = await this.getWikiData([qid]);
        
    const wpUrl = this.wikiData.entities[qid].sitelinks.enwiki.url;
    const wpTitle = wpUrl.split('/').pop();    
        
    // Note &origin=* parameter required for MediaWiki/Wikidata requests

    const wpResponse = await fetch(this.wikipediaAPIUrl + '&origin=*&page=' + wpTitle);
    const wpResult = await wpResponse.json();
    
    if (!wpResponse.ok) {
      const message = `An error has occured: ${wpResponse.status}`;
      throw new Error(message);
    }
    
    this.wikipediaData = wpResult.parse;
    
    return wpResponse; 
  }
  
  render() {
    if(this.wikipediaData.length ==0) {
      this.renderMessage();
      return;
    }
        
    var infoContainer = this.shadowRoot.querySelector('#info');
    while (infoContainer.firstChild) {
      infoContainer.removeChild(infoContainer.firstChild);
    } 
    
    var titleElement = document.createElement('h2');
    titleElement.appendChild(document.createTextNode(this.wikipediaData.title));
      
    var text = document.createElement('div');
    text.classList.add('description');
    
    // Replace internal links with external ones. 
    
    var wptext = String(this.wikipediaData.text).replace(/href=\"\/wiki/g,'href="https://en.wikipedia.org/wiki').replace(/\<a /g,'<a target="_blank" ');
    text.innerHTML = wptext;
        
    infoContainer.appendChild(titleElement);
    infoContainer.appendChild(text);

    
  }
  
  renderMessage(msgTxt) {
    var message = document.createElement('p');
    message.classList.add('message');
    var textElement = document.createTextNode(msgTxt);
    var infoContainer = this.shadowRoot.querySelector('#info');
    // Empty info container
    while (infoContainer.firstChild) {
      infoContainer.removeChild(infoContainer.firstChild);
    } 
    infoContainer.appendChild(textElement);   
  }
  
});


/**
 *  transcript-html element.
 *  Presents an interview transcript with named entity anchors.
 */
 
customElements.define('transcript-html', class extends DDHIInfoPanel {
  constructor() {
    super(); 
    this.selectedEntity;
    this.selectedEntityElements = [];
    this.previousSelectedEntity = null; // Used to detect a change in selected entities.

    // Attach a shadow root to <transcript-html>.
    const shadowRoot = this.attachShadow({mode: 'open'});
    shadowRoot.innerHTML = `
      <style>
        
        :host {
          display: grid;
          grid-template-columns: 1fr;
          grid-template-rows: 3rem 1fr;
          height: 100%;
        }
        
        * {
         font-size: 0.8rem; 
        }
        
        .controls {
          padding: var(--ddhi-viewer-padding, 1rem) 0;
          display: flex;
          flex-direction: row;
        }
        
        .controls a {
          display: flex;
          flex-direction: row;
          position: relative;
          margin-right: var(--ddhi-viewer-padding,1rem);     
        }
        
        .previous, .next {
          font-size: 0.7rem;
          cursor: pointer;
          opacity: 0.7;
        }
        
        .previous:hover, .next:hover {
          opacity: 1;  
        }
        
        .previous.disabled, .next.disabled {
          opacity: 0.3;
          pointer-events: none;
        }
        
        a.next:after {
          position: relative;
          content: '';
          height: 0.3rem;
          width: 0.3rem;
          top: 0.3rem;
          margin-left: 0.25rem;
          background: no-repeat url("data:image/svg+xml;base64,PHN2ZyBpZD0ibmV4dC1idG4iIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDE4LjQ1IDIwIj48ZGVmcz48c3R5bGU+LmNscy0xe2ZpbGw6IzAwMTcxYTt9PC9zdHlsZT48L2RlZnM+PHBhdGggY2xhc3M9ImNscy0xIiBkPSJNMCwyMFYxNC45TDEzLjYzLDEwLDAsNS4xVjBMMTguNDUsNy4zNXY1LjNaIi8+PC9zdmc+");
        }

        a.previous:before {
          position: relative;
          content: '';
          height: 0.3rem;
          width: 0.3rem;
          top: 0.3rem;
          margin-right: 0.25rem;
          background: no-repeat url("data:image/svg+xml;base64,PHN2ZyBpZD0iY2hhcmFjdGVyIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxOC40NSAyMCI+PGRlZnM+PHN0eWxlPi5jbHMtMXtmaWxsOiMwMDE3MWE7fTwvc3R5bGU+PC9kZWZzPjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTE4LjQ1LDBWNS4xTDQuODIsMTAsMTguNDUsMTQuOVYyMEwwLDEyLjY1VjcuMzVaIi8+PC9zdmc+");
        }
        

        
        
        .info {
          overflow: auto;
        }
              
        interview_body {
          display: block;
          overflow-y: scroll;
          height: 100%;
        }
        
        dt, dd {
          line-height: 1.9;
        }
      
        dt {
          margin-bottom: 0;
          font-weight: 800;
          }
          
        dd {
          margin-left: 1rem;
        }
          
        dd span {
          display: inline-block;
          }
          
        dd span[data-entity-type='event'] {
          background-color: #D7E9F7;
        }
          
        dd span[data-entity-type='event']:hover, dd span[data-entity-type='event'].active {
          background-color: #9BC8EB;
          }
          
        dd span[data-entity-type='place'] {
          background-color: #FFF5E7;
        }
          
        dd span[data-entity-type='place']:hover, dd span[data-entity-type='place'].active {
          background-color: #FFC66F;
          }
          
        dd span[data-entity-type='person'] {
          background-color: #F5E7EA;
        }
          
        dd span[data-entity-type='person']:hover, dd span[data-entity-type='person'].active {
          background-color: #CE8A96;
          }
          
        dd date {
          background-color: #CCE1D8;
        }
          

      </style>
      <div class='controls'>
        <a class='previous disabled'>Previous Reference</a> <a class='next disabled'>Next Reference</a>
      </div>
      <div class='info'></div>
    `;
  }
  
  // @method connectedCallback()
  // @description Initializer method for this component.
  
  connectedCallback() {
    var component = this;
  
    super.connectedCallback();
    this.shadowRoot.querySelector('.previous').addEventListener('click', event => {
        if (this.selectedEntity != null) {
          component.decrementSelectedEntityIndex();
          component.focusSelectedEntity();
        }
    });
    this.shadowRoot.querySelector('.next').addEventListener('click', event => {
        if (this.selectedEntity != null) {
          component.incrementSelectedEntityIndex();
          component.focusSelectedEntity();
        }
    });
  }
  
  // @method observedAttributes()
  // @description Lists the attributes to monitor. Listed attributes will
  //   trigger the attributeChangedCallback when their values change.
  // @return An array of monitored attributes.
  
  static get observedAttributes() {
    return ['ddhi-active-id','selected-entity'];
  }

  // @method attributeChangedCallback()
  // @description HTMLElement listener that detects changes to attributes. If the active 
  //   ids are changed it triggers a transcript load process.
  
  async attributeChangedCallback(attrName, oldVal, newVal) {    
    if(attrName == 'ddhi-active-id') {
      await this.getItemDataById();
      this.render();
    }
    
    if(attrName == 'selected-entity') {
      this.selectedEntity = this.hasAttribute('selected-entity') ? this.getAttribute('selected-entity') : null;
      if (this.selectedEntity != null) {
      
        // Enable next and previous controls
        
        this.shadowRoot.querySelector('.previous').classList.remove('disabled');
        this.shadowRoot.querySelector('.next').classList.remove('disabled');
      
        this.getSelectedEntityElements();
        this.highlightSelectedEntity();
        //this.setSelectedEntityIndex();
        this.focusSelectedEntity();
      } else {
      
        // disable next and previous controls
      
        this.shadowRoot.querySelector('.previous').classList.remove('disabled');
        this.shadowRoot.querySelector('.next').classList.remove('disabled');
      }
    }
    
  }
  
  setSelectedEntityIndex() {  
    if (this.previousSelectedEntity == this.selectedEntity) {
      this.incrementSelectedEntityIndex();
    } else {
      this.propagateAttributes('data-entity-index',0); // reset
    } 
    
    
    this.previousSelectedEntity = this.selectedEntity; 
  }
  
  getSelectedEntityIndex() {
    return this.hasAttribute('data-entity-index') ? parseInt(this.getAttribute('data-entity-index')) : 0;
  }  
  
  incrementSelectedEntityIndex() {  
    var index = this.getSelectedEntityIndex() + 1 // increment    
    if (index == this.selectedEntityElements.length) {
      index = 0;
    }
    
    this.propagateAttributes('data-entity-index',index); 
  }
  
  decrementSelectedEntityIndex() {  
    var index = this.getSelectedEntityIndex() - 1; // decrement
    
    if (index < 0) {
      index = this.selectedEntityElements.length - 1;
    }
    
    this.propagateAttributes('data-entity-index',index); 
  }
  
  getSelectedEntityElements() {
    this.selectedEntityElements = this.shadowRoot.querySelectorAll('[data-entity-id="' + this.selectedEntity + '"]');
  }
  
  highlightSelectedEntity() {
    this.shadowRoot.querySelectorAll('[data-entity-id]').forEach(function(e) {
      e.classList.remove('active');
    });
    
        
    this.selectedEntityElements.forEach(function(e) {
      e.classList.add('active');
    });
  }
  
  focusSelectedEntity() {
  
    if (this.selectedEntityElements.length == 0) {
      return;
    }
    
    var interviewElement = this.shadowRoot.querySelector('interview_body');
    var interviewTop = interviewElement.getBoundingClientRect().top;
        
    var topPos = this.selectedEntityElements[this.getSelectedEntityIndex()].offsetTop;
    
    interviewElement.scroll({
      top: topPos - interviewTop - 30, 
      behavior: 'smooth'
    });
    
  
  }
  
  // @method render()
  // @description View display method for this component..
    
  render() {
    var item = this.getItemData();
        
    if (item.hasOwnProperty('transcript')) {
      this.renderValue(this.shadowRoot.querySelector('.info'),item.transcript);
    }
  }
});


/**
 *  ddhi-entity element.
 *  The primary DDHI Viewer web application.
 */

customElements.define('entity-card', class extends DDHIDataComponent {
  constructor() {
    super(); 
    
    this.id;
    this.entityAnchor; // The wrapping anchor tag of the DOM element
    
    // Define the shadow root
    const shadowRoot = this.attachShadow({mode: 'open'});
    shadowRoot.innerHTML = `
      <style>
        :host {
          position: relative;
          width: 3.5rem;
          height: 3.5rem;
          margin: 0 1rem 3.5rem 1rem;
        }
        
        a#entity-link {
          text-decoration: none;
          cursor: pointer;
        }
        
        .entity-icon {
          height: 2rem;
          width: 2rem;
          margin: 0 auto 0.5rem auto;
          border-radius: 0.25rem;
          display: flex;
          justify-content: center;
          align-items: center;
          color: #FFFFFF;
          font-weight: 800;
          font-size: 0.7rem;
        }
                
        :host([data-entity-type='event']) .entity-icon {
          background-color: #9BC8EB;
        }
        
        :host([data-entity-type='place']) .entity-icon {
          background-color: #FFA00F;
        }
        
        :host([data-entity-type='person']) .entity-icon {
          background-color: #9D162E;
        }
                
        .entity-label {
          font-size: 0.7rem;
          text-align: center;
        }
        
        .entity-contents {
          display: none;
        }
        
      </style>
      <a id='entity-link'>
        <div class='entity-icon'>
          <span><slot name='iconlabel'></slot></span>
        </div>
        <div class='entity-label'>
          <slot name='label'></slot>
        </div>
      </a>
      <div class='entity-contents'>
        <slot name='contents'></slot>
      </div>
    `;
    }
    
  async connectedCallback() {
    super.connectedCallback();
    
    this.id = this.getAttribute('data-entity-id');
    this.entityAnchor = this.shadowRoot.querySelector('a#entity-link');
        
    var entitycard = this;
    
    this.entityAnchor
      .addEventListener('click', event => {
        if (entitycard.hasAttribute('data-mention')) {
          entitycard.propagateAttributes('data-entity-index',entitycard.getAttribute('data-mention') - 1);
        }
        entitycard.propagateSelectedEntity(entitycard.id);
      });
    this.entityAnchor
      .addEventListener('touch', event => {
        entitycard.propagateSelectedEntity(entitycard.id);
        if (entitycard.hasAttribute('data-mention')) {
          entitycard.propagateAttributes('data-entity-index',entitycard.getAttribute('data-mention') - 1);
        }
        entitycard.propagateSelectedEntity(entitycard.id);
      });
    }
  
});


/**
 *  ddhi-viewer element.
 *  The primary DDHI Viewer web application.
 */
 
customElements.define('ddhi-viewer', class extends DDHIDataComponent {
  constructor() {
    super(); 
    
    this.visContainer;
    this.infoContainer;
    this.visualizations = [];
    this.infoPanels = [];
    this.titleContainer;
    this.vizcontrols; // Selection mechanism for visualizations
    this.ivcontrols; // Selection mechanism for information view

    // Define the shadow root
    const shadowRoot = this.attachShadow({mode: 'open'});
    shadowRoot.innerHTML = `
      <style>            
        * {
          box-sizing: border-box;
          color: #232526;
        }
                
        :host {
          display: block;
          width: 100%;
          height: 100%;
          font-family: var(--body-font);
          --ddhi-viewer-padding: 1rem;
          --heading-font: "Aleo-Regular", Georgia, serif; 
          --body-font: "Roboto-Regular", Tahoma, sans-serif;
        }
                
        #viewer {
          display: grid;
          width: 100%;
          height: 100%;
          grid-template-rows: 100%;
          grid-template-columns: 10% 52.5% 37.5%
        }
        
        @media screen and (min-width: 62.5em) {
          #viewer {
            min-height: 500px;
          }
        }
        
        @media screen and (min-width: 62.5em) and (max-height: 500px) {
          #viewer {
            height: calc(500px - var(--ddhi-viewer-padding));
          }
        }
        
        @media screen and (min-width: 62.5em) and (min-height: 500px) {
          #viewer {
            max-height: calc(100vh - var(--ddhi-viewer-padding));
          }
        }
        
        section {
          display: flex;
          flex-direction: column;
          height: 100%;
          justify-content: flex-start;
          overflow: hidden;;
          padding: var(--ddhi-viewer-padding)

        }
                
        section#menu > ul {
          overflow-y: scroll; 
        }
                
        #stage > * {
          width: 100%; 
        }
        
        #visualizations {
          height: 100%;
          overflow: hidden;
          flex-shrink: 1;
          flex-grow: 1;
          padding: var(--ddhi-viewer-padding)
        }
        
        ::slotted(div[slot='visualizations']) {
          display: block;
          height: 100%;
          width: 100%;
        }
        
        section#menu {
          border-right: 1px solid var(--ddhi-viewer-border-color,#E9E9E9);
        }
        
                
        section#information-viewer {
          border-left: 1px solid var(--ddhi-viewer-border-color,#E9E9E9);
        }
        
        ::slotted(div[slot='infopanels']) {
          height: 100%;
          overflow-y: hidden;
        }
                
        #stage > header, section#information-viewer header {
          width: 100%;
          height: var(--view-header-height,6rem);
          flex-shrink: 0;
          flex-grow: 0;
          padding-bottom: var(--ddhi-viewer-padding, 1rem);
          border-bottom: 1px solid var(--ddhi-viewer-border-color,#E9E9E9);
        }
        
        #stage > header {
          display: flex;
          flex-direction: row;
          flex-wrap: nowrap;
          justify-content: space-between;
          overflow-y: hidden;
        }
        
        #information-viewer header {
          display: flex;
          flex-direction: row;
          justify-content: space-between;  
        }
        
        #title h2 {
          display: block;
          display: -webkit-box;
          text-overflow: ellipsis;
          -webkit-line-clamp: 1;
          -webkit-box-orient: vertical;
          overflow: hidden;
          font-family: var(--heading-font);
        }
        
        #vizcontrols, #ivcontrols {
          display: flex;
          flex-direction: column;
          justify-content: flex-end;
          height: 100%;
        }
                
        #stage > footer {
          width: 100%;
          height: var(--view-header-height,6rem);
          flex-shrink: 0;
          flex-grow: 1;
          background-repeat: no-repeat;
          background-position: bottom right;
          background-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1NjQuNDggMTAwIj48cGF0aCBkPSJNMCA5OS4zNlY4Mi45NGgxNi40MnYxNi40em00MSAwVjgyLjkxSDI0LjU1djE2LjQzem0yNC41NSAwVjgyLjk0SDQ5LjA5djE2LjQyem0yNC41NCAwVjgyLjg3SDczLjY0Vjk5LjN6bTI0LjU1IDBWODIuODVIOTguMTl2MTYuNDN6bTI0LjU1IDBWODIuOTRoLTE2LjQ2djE2LjQyem0yNC41NCAwVjgyLjgxaC0xNi40NXYxNi40M3ptMjQuNTUgMFY4Mi43OWgtMTYuNDV2MTYuNDN6bTI0LjU0IDBWODIuNzdIMTk2LjR2MTYuNDF6bTI0LjU1IDBWODIuNzVoLTE2LjQydjE2LjQ0ek00MSA3NVY1OC41NEgyNC41OFY3NXptMjQuNTUgMFY1OC41OEg0OS4xM1Y3NXptNDkuMDkgMFY1OC40OUg5OC4xN3YxNi40M3ptMjQuNTUgMFY1OC41OGgtMTYuNDhWNzV6bTI0LjU0IDBWNTguNDVoLTE2LjQ3djE2LjQzem0yNC41NSAwVjU4LjQzaC0xNi40N3YxNi40M3ptNDkuMDkgMFY1OC4zOUgyMjAuOXYxNi40M3pNNjUuNDkgNTEuMzlWMzVINDkuMDZ2MTYuNDF6bTczLjY0IDBWMzQuOTJIMTIyLjd2MTYuNDN6bTI0LjU0IDBWMzQuOWgtMTYuNDN2MTYuNDJ6bS05OC4yLTIzLjU1VjExLjQyTDQ5IDExLjQ0djE2LjQyem03My42NC0uMDVWMTEuMzdoLTE2LjQzVjI3Ljh6bTI0LjU1IDBWMTEuMzdoLTE2LjQ0djE2LjQyem03My42NSAyMy40OVYzNC44NGgtMTYuNDN2MTYuNDN6bTExNy40NiA0MS40MXY2LjUxaC0xNy4xMmEzLjkzIDMuOTMgMCAwMS0zLjgzLTNsLS44OC02LjdjLTYuMjcgNy0xMy41IDEwLjQ4LTIyLjA3IDEwLjQ4LTguMTYgMC0xNC40MS0zLTE5LjEtOXMtNy0xNC44OS03LTI1Ljg4YzAtMTAuMzEgMi42OC0xOC44NCA4LTI1LjM1IDUuNDgtNi44NSAxMi44NC0xMC4zMyAyMS44Ny0xMC4zM2EyNC4zMiAyNC4zMiAwIDAxMTcuNjIgNi45NHYtMjUuM2wtOC4zMi0xLjQ5YTIuOSAyLjkgMCAwMS0yLjU1LTMuMDZWMGgyMy45djg4LjE1YzQuMzguODIgNi40OSAxLjI2IDcuMjIgMS41MWEyLjc0IDIuNzQgMCAwMTIuMjYgMy4wM3pNMzMyLjIgNzkuNDdWNDcuNThhMTcuOTIgMTcuOTIgMCAwMC0xNC44My03LjVjLTYuMjcgMC0xMC45IDIuMDgtMTQuMTggNi4zNnMtNC44OSAxMC40Mi00Ljg5IDE4LjY3YzAgMTIuNSAzLjA3IDE5LjkzIDkuNCAyMi43MWExNy41NCAxNy41NCAwIDAwNyAxLjI3aC4zbC4xMy4xM2M2LjUxLS4wOSAxMi4yNS0zLjM3IDE3LjA3LTkuNzV6bTEwMiAxMy4yMnY2LjUxaC0xNy4xNGEzLjkxIDMuOTEgMCAwMS0zLjgzLTNsLS44OS02LjdjLTYuMjYgNy0xMy40OSAxMC40OC0yMi4wNiAxMC40OC04LjE3IDAtMTQuNDItMy0xOS4xLTlzLTctMTQuODktNy0yNS44OGMwLTEwLjMxIDIuNjgtMTguODQgOC0yNS4zNUMzNzcuNiAzMi45MSAzODUgMjkuNDMgMzk0IDI5LjQzYTI0LjI4IDI0LjI4IDAgMDExNy42MSA2Ljk0VjExLjA2bC04LjMyLTEuNDlhMi45IDIuOSAwIDAxLTIuNTUtMy4wNlYwaDIzLjl2ODguMTVjNC40NC44MyA2LjQ5IDEuMjYgNy4yMyAxLjUxYTIuNzQgMi43NCAwIDAxMi4zMSAzLjAzem0tMjIuNi0xMy4yMlY0Ny41OGExNy45IDE3LjkgMCAwMC0xNC44My03LjVjLTYuMjYgMC0xMC45IDIuMDgtMTQuMTcgNi4zNnMtNC45IDEwLjQyLTQuOSAxOC42N2MwIDEyLjUgMy4wOCAxOS45MyA5LjQgMjIuNzFhMTcuNjEgMTcuNjEgMCAwMDcgMS4yN2guMjlsLjEyLjEzYzYuNTQtLjA5IDEyLjI4LTMuMzcgMTcuMDktOS43NXptMTA3LjQ4IDEwLjMyYy0uNzktLjI2LTMuMTEtLjc2LTcuMDktMS41MVY1NS44M2MwLTcuODMtMi4wNy0xNC40LTYtMTlzLTkuODUtNy4xMy0xNy4yMi03LjEzYy03LjY2IDAtMTQuNDggMy0yMC43OSA5LjFWLjI3aC0yNC4xN3Y2LjI0YTMuMSAzLjEgMCAwMDIuNTkgMy4xN2MuOC4yNyAzLjUxLjc3IDguMjggMS41M3Y3Ny4yMWwtNi44NyAxLjM1YTMuMTMgMy4xMyAwIDAwLTIuNjcgMy4yM3Y2LjJoMzIuMzhWOTNhMy4xMyAzLjEzIDAgMDAtMi42NC0zLjE5Yy0uNjItLjE1LTIuNjgtLjU4LTUuODgtMS4ybC0xLS4xN1Y0OS40N2M1LjQ3LTYuMDYgMTEuMTQtOSAxNy4zNS05IDkgMCAxMy41IDUuMTcgMTMuNSAxNS4zNlY5OS4yaDIyLjcxVjkzYTMgMyAwIDAwLTIuNDgtMy4yMXptMjMtNzMuMTFhMTAuMTEgMTAuMTEgMCAwMDIuODMgMiA4LjY4IDguNjggMCAwMDcgMCAxMC4zMiAxMC4zMiAwIDAwMi44My0yIDEwLjExIDEwLjExIDAgMDAyLTIuODMgNy43MyA3LjczIDAgMDAuODctMy40OSA4LjI0IDguMjQgMCAwMC0uODctMy42MiAxMC42NSAxMC42NSAwIDAwLTItMyAxMC4xMSAxMC4xMSAwIDAwLTIuODMtMiA4LjU4IDguNTggMCAwMC03IDAgMTAuMTEgMTAuMTEgMCAwMC0yLjgzIDIgMTAuODkgMTAuODkgMCAwMC0yIDMgOCA4IDAgMDAtLjczIDMuNjIgNy41OCA3LjU4IDAgMDAuNzMgMy40OSAxMC4xMyAxMC4xMyAwIDAwMi4wMyAyLjgzem0xOS44MyA3Mi45NWwtNy0xLjM1VjMwLjc2aC0yMi42OFYzN2EzLjI1IDMuMjUgMCAwMDIuNTQgMy4xOWw3LjE0IDEuMzV2NDYuNzRsLTcuMTYgMS4zNmEzLjIzIDMuMjMgMCAwMC0yLjUyIDMuMTh2Ni4zOGgzMi4yNXYtNi4zOGEzLjI0IDMuMjQgMCAwMC0yLjU0LTMuMTl6IiBmaWxsPSIjYmRiZWJlIi8+PC9zdmc+");
          background-size: 8rem;
          padding-top: var(--ddhi-viewer-padding, 1rem);
          border-top: 1px solid var(--ddhi-viewer-border-color,#E9E9E9);
          display: flex;
          flex-direction: row;
          justify-content: flex-start;
          align-items: flex-start;
        }
        
        #stage > footer > * {
          width: 50%; 
        }

        
        #media-player {
          width: 50%;
        }
        
        #legend {
          padding-left: var(--ddhi-viewer-padding, 1rem);
        }
        
        #legend-items {
          display: flex;
          flex-direction: row;
          justify-content: flex-end;
          align-items: center;
          font-size: 0.75rem;
        }
        
        #legend-items > * {
          position: relative;
          display: flex;
          flex-direction: row;
          justify-content: flex-start;
          align-items: center;
          margin-right: var(--ddhi-viewer-padding, 1rem);
        }
        
        #legend-items > *:last-child {
          margin-right: 0
        }
        
        #legend-items > *:before {
          content: '';
          height: 1rem;
          width: 1rem;
          background-color: var(--ddhi-viewer-border-color,#E9E9E9);
          margin-right: 0.5rem;
          border-radius: 2px;
        }
        
        #legend-items > .events:before {
          background-color: #9BC8EB;
        }
        
        #legend-items > .places:before {
          background-color: #FFA00F;
        }
        
        #legend-items > .persons:before {
          background-color: #9D162E;
        }
        
        h2 {
          margin: 0 0 0.5rem 0;
          font-family: "Aleo-Regular", Georgia, serif;
          font-size: 1.9rem;
          font-weight: 400;
        }
        
        h3 {
          font-size: 1rem;
          font-weight: 800;
          text-transform: uppercase;
          margin: 0 0 0.5rem 0;
        }
        
        
        #menu header {
          font-size: 0.7rem;
          color: #919293;
          font-weight: 800;
          text-transform: uppercase;
          padding-bottom: var(--ddhi-viewer-padding);
        }
        
        ul#interview-menu {
          padding: 0;
          margin: 0;
          font-size: 0.95rem;
        }
        
        #interview-menu li {
          list-style-type: none;
          font-size: 0.75rem;
          font-weight: 400;
          margin-left: 0;
          padding-left: 0;
          margin-bottom: 0.75rem;
        }
        
        #interview-menu li a.active {
          font-weight: 800; 
        }
        
        #interview-menu li:hover {
          text-decoration: underline; 
        }
        
        #interview-menu a {
          cursor: pointer;
        }
        
        metadata-field {
          display: inline-block;
          margin-right: 1rem;
        }
        
        .metadata-field .label {
          text-transform: uppercase;
          font-size: 0.75rem;
          color: #919293;
          font-weight: 800;
          display: inline-block;
          margin-right: 0.25rem;
        }
        
        .metadata-field .value {
          font-size: 0.75rem;
          color: #4F5152;
        }
        
        .formlabel {
          color: #99A2A3;
          font-size: 0.75rem;
        }
        
        select {
          -webkit-appearance: none;
          -webkit-border-radius: 0;
          border-width: 0 0 2px 0;
          border-bottom-color: #9BC8EB;
          height: 2rem;
          width: 15rem;
          text-transform: uppercase;
          font-weight: 800;
          font-size: 0.75rem;
          padding-left: 0
        }
        
        #tei-link a {
          display: block;
          cursor: pointer;
          height: 30px;
          width: 43px;
          background: no-repeat url("data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCEtLSBHZW5lcmF0b3I6IEFkb2JlIElsbHVzdHJhdG9yIDI1LjMuMSwgU1ZHIEV4cG9ydCBQbHVnLUluIC4gU1ZHIFZlcnNpb246IDYuMDAgQnVpbGQgMCkgIC0tPgo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IgoJIHZpZXdCb3g9IjAgMCAzMCA0Mi44IiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCAzMCA0Mi44OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+CjxzdHlsZSB0eXBlPSJ0ZXh0L2NzcyI+Cgkuc3Qwe2ZpbGw6IzIzMjUyNjt9Cgkuc3Qxe2ZvbnQtZmFtaWx5OidSb2JvdG8tUmVndWxhcic7fQoJLnN0Mntmb250LXNpemU6Ni4zOTQzcHg7fQo8L3N0eWxlPgo8Zz4KCTxnPgoJCTx0ZXh0IHRyYW5zZm9ybT0ibWF0cml4KDEgMCAwIDEgMi40NDE0MDZlLTA0IDMzLjg1OTkpIj48dHNwYW4geD0iMCIgeT0iMCIgY2xhc3M9InN0MCBzdDEgc3QyIj5Eb3dubG9hZDwvdHNwYW4+PHRzcGFuIHg9IjEwLjEiIHk9IjcuMiIgY2xhc3M9InN0MCBzdDEgc3QyIj5URUk8L3RzcGFuPjwvdGV4dD4KCTwvZz4KCTxwYXRoIGNsYXNzPSJzdDAiIGQ9Ik0yNy40LDE1LjlIMi43Yy0xLDAtMS45LDAuOS0xLjksMS45djMuOGMwLDEsMC45LDEuOSwxLjksMS45aDI0LjdjMSwwLDEuOS0wLjksMS45LTEuOXYtMy44CgkJQzI5LjMsMTYuNywyOC41LDE1LjksMjcuNCwxNS45eiBNMjMuOSwyMi4xYy0wLjUsMC0xLTAuNC0xLTAuOWMwLTAuNSwwLjQtMC45LDEtMC45YzAuNSwwLDEsMC40LDEsMC45CgkJQzI0LjksMjEuNiwyNC40LDIyLjEsMjMuOSwyMi4xeiBNMjYuOCwyMi4xYy0wLjUsMC0xLTAuNC0xLTAuOWMwLTAuNSwwLjQtMC45LDEtMC45YzAuNSwwLDEsMC40LDEsMC45CgkJQzI3LjcsMjEuNiwyNy4zLDIyLjEsMjYuOCwyMi4xeiBNNi4zLDYuOGw2LjgsMy44djIuOUw0LjIsNy44VjUuN0wxMywwdjIuOUw2LjMsNi44eiBNMjMuOSw2LjdsLTcuMS0zLjlWMEwyNiw1LjZ2Mi4ybC05LjIsNS43CgkJdi0yLjlMMjMuOSw2Ljd6Ii8+CjwvZz4KPC9zdmc+Cg==");
          opacity: 0.7;
        }
        
        #tei-link a:hover {
          opacity: 1;  
        }
          
        
          
        
        
      </style>
      <div id='viewer'>
        <section id='menu' propagate>
          <header>Select an interview:</header>
          <ul id='interview-menu'></ul>
        </section>
        <section id='stage'>
          <header>
            <div id='title'></div>
            <div id='vizcontrols'><select></select><div class='formlabel'>Select a visualization.</div></div>
          </header>
          <div id='visualizations'>
            <slot name='visualizations'></slot>
          </div>
          <footer>
            <div id='media-player' propagate>
              <audio
                controls
                src="https://ddhi.agilehumanities.ca/sample-audio/alverson_hoyt.mp3">
                    Your browser does not support the
                    <code>audio</code> element.
              </audio>
            </div>
            <div id='legend'>
              <div id='legend-items'>
                <div class='events'>Events</div>
                <div class='persons'>Persons</div>
                <div class='places'>Places</div>
              </div>
            </div>
          </footer>
        </section>
        <section id='information-viewer'>
          <header>
            <div id='ivcontrols'><select></select><span class='formlabel'>Select an information display.</span></div>
            <div id='tei-link'><a title='Download TEI XML File' download></a></div>
          </header>
          <slot id='infopanels' name='infopanels'></slot>
        </section>
      </viewer>
    `;
  }
  
  // @method connectedCallback()
  // @description Initializer method for this component.
  
  async connectedCallback() {
    super.connectedCallback();
    
    // this.viewer is used in the parent Data componentês propagation system and
    // is derived from a selection query of an elementês parents. This will return
    // null for the viewer component itself, so it must be explicitly set.
    
    this.viewer = this;
    
    // localized version for subroutines.
    
    var viewer = this;
    
    
    // Assign viewer header
    
    this.titleContainer = this.shadowRoot.getElementById('title');
    
    // Set up panel selection mechanisms (options set in registerUserComponents)
    
    this.vizcontrols = this.shadowRoot.getElementById('vizcontrols').querySelector('select');
    
    this.ivcontrols = this.shadowRoot.getElementById('ivcontrols').querySelector('select');

            
    // Register User Visualizations and Info Panels
    
    await this.registerUserComponents();
    
    // Set up controls
    
    this.initializePanelSwitching();
            
    // Populate transcripts from REST api
    
    await this.getTranscripts();
    
        
    // Set Active Menu
            
    var menu = this.shadowRoot.getElementById('interview-menu');
    
    for(var i=0;i<this.availableIds.length;i++) {
      var listEl = document.createElement('li');
      var aEl = document.createElement('a');
      aEl.setAttribute('data-id',this.availableIds[i].id);
      aEl.appendChild(document.createTextNode(this.availableIds[i].title));
      aEl.addEventListener('click', event => {
        var element = event.currentTarget;
        var transcriptID = element.getAttribute('data-id');
        
        menu.querySelectorAll('.active').forEach(function(e){
          e.classList.remove('active');
        });
        
        element.classList.add('active');
        this.deactivateIds();
        this.activateId(transcriptID);
        
        /*
        Stashed logic for multiple active transcripts.
        
        if (element.classList.contains('active')) {
          this.deactivateIds(transcriptID);
          element.classList.remove('active');
        } else {
          this.activateId(transcriptID);
          element.classList.add('active');
        }*/
      });
      
      listEl.appendChild(aEl);
      menu.appendChild(listEl);
    }
    
    // Fire click event on first menu item
    
    var evObj = document.createEvent('Events');
    evObj.initEvent('click', true, false);
    menu.querySelector('a').dispatchEvent(evObj);
    
  }
  
  // @method activateId()
  // @description Adds a transcript to the active list and triggers propagation.
  
  activateId(id) {
    const index = this.activeIds.indexOf(id);
    if (index == -1) {
      this.activeIds.push(id);
    }
    this.propagateActiveIds();
  }
  
  // @method deactivateIds()
  // @description Remove all active IDs. Will not trigger propagation unless an id is supplied.
  // @param id  Deactivates the supplied id and triggers propagation
  
  deactivateIds(id=null) {
  
    if (id==null) {
      this.activeIds = [];
    } else {
      const index = this.activeIds.indexOf(id);
      if (index > -1) {
        this.activeIds.splice(index, 1);
      }
      this.propagateActiveIds();
    }
  }

  // @method observedAttributes()
  // @description Lists the attributes to monitor. Listed attributes will
  //   trigger the attributeChangedCallback when their values change.
  // @return An array of monitored attributes.

  static get observedAttributes() {
    return ['ddhi-active-id','selected-entity'];
  }

  
  // @method observedAttributes()
  // @description Lists the attributes to monitor. Listed attributes will
  //   trigger the attributeChangedCallback when their values change.
  // @return An array of monitored attributes.
  
  async attributeChangedCallback(attrName, oldVal, newVal) {
    if(attrName == 'ddhi-active-id') {
      await this.getItemDataById();
      
      await this.getTEI(this.getAttribute('ddhi-active-id'));
      
      this.teiLink = this.teiResource.filepath;
      
      var teiLinkElement = this.shadowRoot.getElementById('tei-link').querySelector('a');
      teiLinkElement.setAttribute('href', this.teiLink);
      
      this.render();
    }
  }


  async getTEI(id,format='json') {
        
    const response = await fetch(this.apiURI + '/items/' + id + '/tei?_format=' + format, {mode: 'cors'});
    const result = await response.json();
             
    if (!response.ok) {
      const message = `An error has occured: ${response.status}`;
      throw new Error(message);
    }
    
    this.teiResource = result;
    
    return response; 
    
  }

  
  // @method registerUserComponents()
  // @description  Registers user components like visualizations and infoPanels.
  //   setTimeout waits for the DOM to be rendered. A promise is
  //   then created to ensure that object properties were set.
  
  async registerUserComponents() {
  
    var viewer = this;
  
    var componentsReady = new Promise(function(resolve) {
  
      setTimeout(function() { 
        [... viewer.children].forEach(function(e){
          if (e.getAttribute('slot')=='visualizations') {
            viewer.visContainer = e;
            viewer.visualizations = [... e.children];
            
            viewer.visualizations.forEach(function(e,i) {
              var option = document.createElement('option')
              option.setAttribute('value',i);
              option.appendChild(document.createTextNode(e.getAttribute('data-label')));
              viewer.vizcontrols.appendChild(option);
            });
            
          }
      
          if (e.getAttribute('slot')=='infopanels') {
            viewer.infoContainer = e;
            viewer.infoPanels = [... e.children];
            
            viewer.infoPanels.forEach(function(e,i) {
              var option = document.createElement('option')
              option.setAttribute('value',i);
              option.appendChild(document.createTextNode(e.getAttribute('data-label')));
              viewer.ivcontrols.appendChild(option);
            });
          }
          
          resolve();
        });
      }, 100);    
    
    });
    
    await componentsReady;
    
    //await infoPanels;

  }
  
  initializePanelSwitching() {
    var viewer = this;
    
        
    viewer.visualizations.forEach(function(e,i) {
      // set panel height
      
      e.style.height = '100%';
      
      // hide panels;
      if (i > 0) {
        e.style.display = 'none';
      } else {
        e.style.display = 'block';
      }
    }); 
    
    
    // Add change listeners that trigger switching
    
    
    viewer.vizcontrols.addEventListener('change', event => {
        var element = event.currentTarget;
        viewer.visualizations.forEach(function(e,i) {
          e.style.display = 'none';
          e.removeAttribute('foreground')
        }); 
                
        viewer.visualizations[event.target.value].style.display = 'block';
        viewer.visualizations[event.target.value].setAttribute('foreground','')
  
    });
    
    viewer.ivcontrols.addEventListener('change', event => {
        var element = event.currentTarget;
        viewer.infoPanels.forEach(function(e,i) {
          e.style.display = 'none';
          e.removeAttribute('foreground')
        }); 
                
        viewer.infoPanels[event.target.value].style.display = 'block';
        viewer.infoPanels[event.target.value].setAttribute('foreground','');
    });
    
    
  }
  
  
  // @method propagateActiveIds()
  // @description Propagates the current active transcripts to the visualizations in 
  //   the form of an attribute. The change should trigger an attribute change listener 
  //   and fire the componentês handler. 
  
  
  propagateActiveIds() {
    this.propagateAttributes('ddhi-active-id',this.activeIds.join());
  }
  
  // @method propagateSelectedEntity()
  // @description Propagates the id of a selected entity 
  

  
  
  // @method render()
  // @description View method to display the component.
  
  render() {
    var item = this.getItemData();
    
    // Create Header
  
    var header = document.createElement('div');
    
    var heading = document.createElement('h2')
    heading.appendChild(document.createTextNode(item.title.replace('Transcript of an Interview with a','').replace('Transcript of an Interview with',''))); // @todo: remove this ugly duct tape
    
    
    var idLabel = document.createElement('span');
    idLabel.classList.add('label');
    idLabel.appendChild(document.createTextNode('ID'));
    
    var idValue = document.createElement('span');
    idValue.classList.add('value');
    idValue.appendChild(document.createTextNode(item.id))
    
    var idWrapper = document.createElement('span');
    idWrapper.classList.add('metadata-field');
    idWrapper.appendChild(idLabel);
    idWrapper.appendChild(idValue);
    
    var metadata = document.createElement('span');
    metadata.classList.add('metadata');
    metadata.appendChild(idWrapper);
  
    header.appendChild(heading);
    header.appendChild(metadata);
        
    this.renderValue(this.titleContainer,header.outerHTML); 
  }
  
});


/**
 *  ddhi-entity-map element.
 *  Basic visualization for the entity browser. Will also serve as a model for other
 *  visualizations
 */
 
customElements.define('ddhi-entity-map', class extends DDHIVisualization {
  constructor() {
    super();
    this.mapElement; // Container
    this.map = null; // Leaflet map
    this.associatedPlaces;
        
    
    // Attach a shadow root to <ddhi-entity-browser>.
    const shadowRoot = this.attachShadow({mode: 'open'});
    shadowRoot.innerHTML = `
      <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
     integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
     crossorigin=""/>
      <style>
        #mapid {
          width: 100%;
          height: 100%;
        }
      </style>
      <div id="mapid"></div>
    `;
    
    var leafletJS = document.createElement('script');
    leafletJS.setAttribute('src','https://unpkg.com/leaflet@1.7.1/dist/leaflet.js');
    leafletJS.setAttribute('integrity','sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA==');
    leafletJS.setAttribute('crossorigin','');
    this.shadowRoot.appendChild(leafletJS);

    }
  
  // @method connectedCallback()
  // @description Initializer method for this component.
  
  connectedCallback() {
    super.connectedCallback();
    this.mapElement = this.shadowRoot.querySelector('#mapid');
  }

    
  // @method observedAttributes()
  // @description Lists the attributes to monitor. Listed attributes will
  //   trigger the attributeChangedCallback when their values change.
  // @return An array of monitored attributes.
  
  static get observedAttributes() {
    return ['ddhi-active-id','selected-entity','foreground'];
  }

  // @method attributeChangedCallback()
  // @description HTMLElement listener that detects changes to attributes. If the active 
  //   ids are changed it triggers a transcript load process.
  
  async attributeChangedCallback(attrName, oldVal, newVal) {    
    if(attrName == 'ddhi-active-id') {
      this.associatedPlaces;
      await this.getAssociatedEntitiesByType(this,'associatedPlaces',this.getActiveIdFromAttribute(),'places');
      this.createLeafletMap();
    }
    
    if(attrName == 'foreground' && this.map !== null) {
      this.map.invalidateSize();
    }
  }
  
  renderMarkerImage() {
    return "iVBORw0KGgoAAAANSUhEUgAAABUAAAAVCAYAAACpF6WWAAAACXBIWXMAAAsSAAALEgHS3X78AAAAgElEQVQ4jWP8//8/w5yTbwQYGBgMGCgDF1LMRT6ATGCcfeI1yLADDAwM/BQa+pGBgcEhxVzkAhMDA8MGKhjIADXjAMyl/6lgIDJwZKKygWAwauiooaOGjho6qA19ADJ0IhUNPJhiLvKAKcVcpABqMKg6IBeA9C5kYGAIYGBgYAAAnd0bgt9wuMEAAAAASUVORK5CYII=";
  }
  
  createLeafletMap() {
    var component = this;
    
    // Previous map
    if (this.map !== null) {
      this.map.off();
      this.map.remove();
    }
  
    // initialize Leaflet
    this.map = L.map(this.mapElement).setView({lon: 0, lat: 0}, 2);
    
    // Create icon
    
    var Icon = L.Icon.extend({
      options: {
         iconSize:     [20, 20],
         shadowSize:   [20, 20],
         iconAnchor:   [10, 10],
         shadowAnchor: [7, 7],
         popupAnchor:  [0, -20]
      }
    });
    
    
    var markerIcon = new Icon ({
      iconUrl: this.viewer.getAttribute('repository') + '/' + this.cdnAssetPath + '/img/svg/icons/place-icon.svg',
      shadowUrl: this.viewer.getAttribute('repository')  + '/' + this.cdnAssetPath + '/img/svg/icons/place-icon-shadow.svg'
    });

    // add the OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap contributors</a>'
    }).addTo(component.map);

    // show the scale bar on the lower left corner
    L.control.scale().addTo(component.map);  
        
    this.associatedPlaces.forEach(function(e,i){
      if (e.location) {      
      
        var marker = L.marker([e.location.lat,e.location.lng], {icon: markerIcon, id: e.id}).addTo(component.map);
        
        marker.bindPopup(e.title).on('click',function(e){
          if (e.target.options.id != null) {
            component.propagateAttributes('data-entity-index',0);
            component.propagateAttributes('selected-entity',e.target.options.id);
          }
        });
     }
    });
  }
    
    
  });