import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const adminSource=readFileSync(new URL('../js/rentals-admin.js',import.meta.url),'utf8');
const adminRenderer=adminSource.slice(adminSource.indexOf('const state = day.past'),adminSource.indexOf("cell.addEventListener('click'"));
const renderAdmin=day=>{
 const cell={disabled:false,attrs:{},children:[],append(...nodes){this.children.push(...nodes);},setAttribute(k,v){this.attrs[k]=v;}};
 vm.runInNewContext(adminRenderer,{day,cell,document:{createElement:()=>({textContent:''})}});
 return cell;
};
for(const day of [
 {date:'2026-10-01',past:true,admin_blocked:false,reserved:0,remaining:5},
 {date:'2026-10-05',past:true,admin_blocked:true,reserved:2,remaining:0}
]){
 const cell=renderAdmin(day);
 assert.equal(cell.className,'is-past');assert.equal(cell.children[1].textContent,'Past / unavailable');
 assert.match(cell.attrs['aria-label'],/Past \/ unavailable/);
 assert.equal(cell.disabled,false,'Historical manual blocks must still be manageable.');
 if(day.admin_blocked)assert.match(cell.title,/Admin blocked/);
}
assert.equal(renderAdmin({date:'2026-10-06',past:false,admin_blocked:false,reserved:0,remaining:5}).children[1].textContent,'Available');
const adminPartial=renderAdmin({date:'2026-10-07',past:false,admin_blocked:false,reserved:2,remaining:3});
assert.equal(adminPartial.children[1].textContent,'3 available');assert.match(adminPartial.attrs['aria-label'],/Reserved · 3 available/);
assert.equal(renderAdmin({date:'2026-10-08',past:false,admin_blocked:false,reserved:5,remaining:0}).children[1].textContent,'Fully reserved');
assert.equal(renderAdmin({date:'2026-10-09',past:false,admin_blocked:true,reserved:0,remaining:0}).children[1].textContent,'Admin blocked');
console.log('PASS: Admin past/today/partial/full/blocked labels; historical manual-block management preserved.');
const formInit=adminSource.slice(adminSource.indexOf("document.querySelectorAll('[data-rental-product-form]')"));
for(const initial of ['0','1']){
 const listeners=[];const type={value:initial,addEventListener:(event,fn)=>listeners.push({event,fn})};
 const groups=Array.from({length:3},()=>({hidden:false,input:{disabled:false,value:'retain value'},querySelectorAll(){return [this.input];}}));
 const labels=[{dataset:{equipmentText:'Name',serviceText:'Service name'},textContent:''}];
 const placeholders=[{dataset:{equipmentPlaceholder:'Sony camera',servicePlaceholder:'Event camera crew'},value:'Preserve entered name',placeholder:''}];
 const guidance={hidden:true};const heading={textContent:''};
 const form={querySelector:()=>type,closest:()=>({querySelector:()=>heading}),querySelectorAll:selector=>({
  '[data-equipment-field]':groups,'[data-product-label]':labels,'[data-product-placeholder]':placeholders,'[data-service-field]':[guidance]
 }[selector]??[])};
 vm.runInNewContext(formInit,{document:{querySelectorAll:()=>[form]}});
 assert.equal(listeners.length,1);assert.equal(listeners[0].event,'change');
 for(const selected of [initial,'1','0','1']){
  type.value=selected;listeners[0].fn();
  assert.equal(heading.textContent,selected==='1'?'Add service':'Add equipment');
  assert.equal(labels[0].textContent,selected==='1'?'Service name':'Name');
  assert.equal(placeholders[0].placeholder,selected==='1'?'Event camera crew':'Sony camera');
  assert.equal(placeholders[0].value,'Preserve entered name');assert.equal(guidance.hidden,selected!=='1');
  for(const g of groups){assert.equal(g.hidden,selected==='1');assert.equal(g.input.disabled,selected==='1');assert.equal(g.input.value,'retain value');}
 }
}
const source=readFileSync(new URL('../js/rentals.js',import.meta.url),'utf8');
const renderer=source.slice(source.indexOf('const state = day.admin_blocked'),source.indexOf('if (day.date === startInput.value'));
const render=day=>{
 const cell={className:'',disabled:false,attrs:{},setAttribute(k,v){this.attrs[k]=v;}};
 vm.runInNewContext(renderer,{day,cell,quantityInput:{value:'1',disabled:false}});return cell;
};
for(const zone of ['Asia/Manila','UTC','America/Los_Angeles','Europe/London']){
 process.env.TZ=zone;
 for(const date of ['2026-10-10','2026-10-11','2026-10-12','2026-10-31','2026-11-01','2026-12-31','2027-01-01']){
  const blocked=render({date,admin_blocked:true,past:false,available:false,reserved:0,remaining:0});
  assert.equal(blocked.className,'is-blocked');assert.equal(blocked.disabled,true);assert.equal(blocked.attrs['aria-label'],`${date}: Admin blocked`);
 }
 const past=render({date:'2026-10-04',admin_blocked:false,past:true,available:false,reserved:0,remaining:3});
 assert.equal(past.className,'is-past');assert.match(past.attrs['aria-label'],/Past date/);
 const partial=render({date:'2026-10-13',admin_blocked:false,past:false,available:true,reserved:1,remaining:2});
 assert.equal(partial.className,'is-reserved');assert.equal(partial.disabled,false);assert.equal(partial.attrs['aria-label'],'2026-10-13: Partially reserved; 2 available');
 const full=render({date:'2026-10-14',admin_blocked:false,past:false,available:false,reserved:3,remaining:0});
 assert.equal(full.className,'is-reserved');assert.equal(full.disabled,true);
}
const helpers=vm.runInNewContext(`${source.slice(0,source.indexOf('const initRentalsCatalogue ='))}\n({limitRentalQuantity,rentalRangeCapacity})`);
const input={value:'6',max:'999',disabled:false};
assert.equal(helpers.limitRentalQuantity(input,5),5);assert.equal(input.value,'5');assert.equal(input.max,'5');
assert.equal(helpers.limitRentalQuantity(input,3),3);assert.equal(input.value,'3');
assert.equal(helpers.limitRentalQuantity(input,0),0);assert.equal(input.disabled,true);assert.equal(input.max,'0');
helpers.limitRentalQuantity(input,5);assert.equal(input.disabled,false);
const dates=[
 {date:'2026-10-30',available:true,remaining:5,reserved:0},
 {date:'2026-10-31',available:true,remaining:3,reserved:2},
 {date:'2026-11-01',available:true,remaining:4,reserved:1},
 {date:'2026-11-02',available:true,remaining:5,reserved:0},
 {date:'2026-11-03',available:false,remaining:0,reserved:5}
];
for(const zone of ['Asia/Manila','UTC','America/Los_Angeles','Europe/London']){
 process.env.TZ=zone;
 const checkRange=(start,end)=>helpers.rentalRangeCapacity(start,end,async month=>dates.filter(day=>day.date.startsWith(month)));
 assert.equal(await checkRange('2026-10-30','2026-10-30'),5);
 assert.equal(await checkRange('2026-10-31','2026-10-31'),3);
 assert.equal(await checkRange('2026-10-30','2026-11-01'),3);
 assert.equal(await checkRange('2026-11-01','2026-11-02'),4);
 assert.equal(await checkRange('2026-11-01','2026-11-03'),0);
 assert.equal(await checkRange('2026-11-02','2026-11-01'),0);
}
// A typed overstock value is clamped by input handling, which can suppress native change.
// Blur must still persist the corrected value exactly once after checking availability.
const quantityEvents={};let cartSaves=0;let availabilityReads=0;
const cartQuantity={value:'1',max:'3',disabled:false,addEventListener:(event,fn)=>{const prior=quantityEvents[event];quantityEvents[event]=prior?()=>{prior();fn();}:fn;}};
const cartStart={value:'2026-10-16',addEventListener(){}};
const cartEnd={value:'2026-10-18',addEventListener(){}};
const cartHelp={textContent:''};
const checkoutEvents={};const checkoutAttrs={};
const checkoutLink={addEventListener:(event,fn)=>{checkoutEvents[event]=fn;},setAttribute:(key,value)=>{checkoutAttrs[key]=value;}};
const cartForm={elements:{quantity:cartQuantity,rental_start_date:cartStart,rental_end_date:cartEnd,id:{value:'1'},line:{value:'self'}},
 dataset:{availabilityUrl:'/rentals/availability'},querySelector:()=>cartHelp,querySelectorAll:()=>[cartQuantity,cartStart,cartEnd],
 checkValidity:()=>true,requestSubmit:()=>{cartSaves++;},addEventListener(){}};
const cartInit=source.slice(source.indexOf('const initRentalCart ='),source.indexOf('const initRentalCheckout ='));
vm.runInNewContext(`${source.slice(0,source.indexOf('const initRentalsCatalogue ='))}\n${cartInit}\ninitRentalCart();`,{
 document:{querySelector:()=>checkoutLink,querySelectorAll:()=>[cartForm]},URL,location:{href:'http://127.0.0.1/rentals/cart'},
 fetch:async url=>{availabilityReads++;assert.equal(url.searchParams.get('line'),'self');assert.equal(url.searchParams.get('quantity'),'1');
  return {ok:true,json:async()=>({ok:true,days:[16,17,18].map(day=>({date:`2026-10-${day}`,available:true,remaining:3}))})};}
});
cartQuantity.value='8';quantityEvents.input();assert.equal(cartQuantity.value,'3');
assert.equal(checkoutAttrs['aria-disabled'],'true');let prevented=0;
checkoutEvents.click({preventDefault:()=>{prevented++;}});assert.equal(prevented,1);
quantityEvents.blur();quantityEvents.blur();
await new Promise(resolve=>setImmediate(resolve));
assert.equal(cartSaves,1);assert.equal(availabilityReads,1);
quantityEvents.blur();assert.equal(cartSaves,1);
console.log('PASS: type controls restored; date labels unchanged in four timezones; partial stock selectable, fully reserved disabled; quantity clamps to stock/date minimum; zero stock disabled; inclusive month-boundary capacity correct; clamped cart input persists once.');
