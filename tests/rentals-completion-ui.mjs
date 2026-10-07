import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=readFileSync(new URL('../js/rentals.js',import.meta.url),'utf8');
const start=source.indexOf('const createRentalGallery =');
const end=source.indexOf('const chosen =',start);
const element=()=>({listeners:{},dataset:{rentalsFallback:'/placeholder.svg'},hidden:false,addEventListener(event,fn){this.listeners[event]=fn;},removeAttribute(){},setPointerCapture(){}});
const visual=element(),picture=element(),controls=element(),count=element(),status=element(),prev=element(),next=element();
const nodes={'.rentals-detail__visual':visual,'[data-detail-image]':picture,'[data-gallery-controls]':controls,'[data-gallery-count]':count,'[data-rentals-image-status]':status,'[data-gallery-prev]':prev,'[data-gallery-next]':next};
const context={};vm.createContext(context);vm.runInContext(source.slice(start,end)+'; this.create=createRentalGallery;',context);
const gallery=context.create({querySelector:selector=>nodes[selector]});
gallery.open({name:'Camera',images:['/one.jpg','/two.jpg'],hasImageReference:true});
assert.equal(picture.src,'/one.jpg');assert.equal(count.textContent,'1 / 2');assert.equal(picture.draggable,false);assert.equal(picture.tabIndex,0);
next.listeners.click();assert.equal(picture.src,'/two.jpg');next.listeners.click();assert.equal(picture.src,'/one.jpg');
prev.listeners.click();assert.equal(picture.src,'/two.jpg');
visual.listeners.keydown({key:'ArrowLeft',preventDefault(){}});assert.equal(picture.src,'/one.jpg');
visual.listeners.pointerdown({target:picture,clientX:200,clientY:50,pointerId:1});visual.listeners.pointerup({clientX:100,clientY:55,pointerId:1});assert.equal(picture.src,'/two.jpg','Swipe left must show the next picture.');
visual.listeners.pointerdown({target:picture,clientX:100,clientY:50,pointerId:2});visual.listeners.pointerup({clientX:110,clientY:200,pointerId:2});assert.equal(picture.src,'/two.jpg','Vertical scrolling must not change picture.');
visual.listeners.pointerdown({target:picture,clientX:100,clientY:50,pointerId:3});visual.listeners.pointercancel();visual.listeners.pointerup({clientX:250,clientY:55,pointerId:3});assert.equal(picture.src,'/two.jpg');
gallery.open({name:'Single',images:['/one.jpg'],hasImageReference:true});assert.equal(controls.hidden,true);next.listeners.click();assert.equal(picture.src,'/one.jpg');
gallery.open({name:'Missing',images:[],hasImageReference:true});assert.equal(picture.src,'/placeholder.svg');assert.equal(status.hidden,false);
gallery.open({name:'Unassigned',images:[],hasImageReference:false});assert.equal(status.hidden,true);
console.log('PASS: gallery arrows, wraparound, keyboard, horizontal swipe, vertical-scroll preservation, pointer cancellation, single/missing image states.');
const admin=readFileSync(new URL('../js/rentals-admin.js',import.meta.url),'utf8');
let onChange;const select={selectedOptions:[{dataset:{summary:'Oct 7, 2026 · ₱1,250.50'}}],addEventListener(event,fn){assert.equal(event,'change');onChange=fn;}};
const output={textContent:''};const chart={querySelector:selector=>selector==='[data-chart-period]'?select:output};
vm.runInNewContext(admin.slice(admin.indexOf('// Exact calendar labels')),{document:{querySelectorAll:()=>[chart]}});
onChange();assert.equal(output.textContent,'Oct 7, 2026 · ₱1,250.50');select.selectedOptions=[];onChange();assert.equal(output.textContent,'');
console.log('PASS: chart date selection updates exact values without timezone/date parsing.');

// Exercise the actual catalogue initializer with both sidebar and toolbar controls.
const catalogueCode=source.slice(source.indexOf('const initRentalsCatalogue ='),source.indexOf('  const createRentalGallery ='))
  +source.slice(source.indexOf('const chosen ='),source.indexOf("  const dialog = document.querySelector('[data-rentals-dialog]');"))
  +'\n}; initRentalsCatalogue();';
const categoryFixture=(query='')=>{
 const buttons=['all','camera','lighting','audio','all','camera','lighting','audio'].map(category=>{
  const classes=new Set(category==='all'?['is-active']:[]);
  return {dataset:{rentalsFilter:category},listeners:{},attrs:{},classList:{contains:c=>classes.has(c),toggle(c,active){active?classes.add(c):classes.delete(c);}},addEventListener(e,fn){this.listeners[e]=fn;},setAttribute(k,v){this.attrs[k]=v;}};
 });
 const cards=[['camera','Camera'],['lighting','Event lights'],['audio','Event microphone']].map(([category,textContent])=>({dataset:{category},textContent,hidden:false}));
 const search={value:'',listeners:{},addEventListener(e,fn){this.listeners[e]=fn;}};
 const results={dataset:{rentalsResultNoun:'item'},textContent:''};
 const more={open:false,querySelector:()=>buttons[7].classList.contains('is-active')?buttons[7]:null};
 const document={documentElement:{dataset:{}},querySelector:s=>s==='[data-rentals-search]'?search:s==='[data-rentals-results]'?results:s==='[data-rentals-filter].is-active'?buttons.find(b=>b.classList.contains('is-active')):null,
  querySelectorAll:s=>s==='[data-rentals-filter]'?buttons:s==='[data-rentals-item]'?cards:s==='[data-rentals-category-more]'?[more]:[]};
 vm.runInNewContext(catalogueCode,{document,window:{location:{search:query}},URLSearchParams});
 return {buttons,cards,search,results,more};
};
const categories=categoryFixture();
assert.equal(categories.results.textContent,'3 items found');
categories.buttons[6].listeners.click();
assert.equal(categories.buttons[2].attrs['aria-pressed'],'true');assert.equal(categories.buttons[6].attrs['aria-pressed'],'true');
assert.deepEqual(categories.cards.map(c=>c.hidden),[true,false,true]);
categories.search.value='missing';categories.search.listeners.input();assert.match(categories.results.textContent,/No matching equipment/);
categories.search.value='';categories.search.listeners.input();
categories.buttons[3].listeners.click();assert.equal(categories.more.open,true);assert.equal(categories.buttons[7].attrs['aria-pressed'],'true');
categories.buttons[4].listeners.click();assert.equal(categories.results.textContent,'3 items found');assert.equal(categories.buttons[0].attrs['aria-pressed'],'true');
const linkedCategory=categoryFixture('?category=audio');assert.equal(linkedCategory.more.open,true);assert.equal(linkedCategory.buttons[3].attrs['aria-pressed'],'true');assert.equal(linkedCategory.buttons[7].attrs['aria-pressed'],'true');
assert.equal(categoryFixture('?category=unknown').results.textContent,'3 items found');
console.log('PASS: sidebar/toolbar category synchronization, search intersection, All reset, expanded-category reveal and deep links.');
