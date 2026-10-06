import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const adminSource=readFileSync(new URL('../js/rentals-admin.js',import.meta.url),'utf8');
const formInit=adminSource.slice(adminSource.indexOf("document.querySelectorAll('[data-rental-product-form]')"));
for(const initial of ['0','1']){
 const listeners=[];const type={value:initial,addEventListener:(event,fn)=>listeners.push({event,fn})};
 const groups=Array.from({length:3},()=>({hidden:false,input:{disabled:false,value:'retain value'},querySelectorAll(){return [this.input];}}));
 const form={querySelector:()=>type,querySelectorAll:()=>groups};
 vm.runInNewContext(formInit,{document:{querySelectorAll:()=>[form]}});
 assert.equal(listeners.length,1);assert.equal(listeners[0].event,'change');
 for(const selected of [initial,'1','0','1']){
  type.value=selected;listeners[0].fn();
  for(const g of groups){assert.equal(g.hidden,selected==='1');assert.equal(g.input.disabled,selected==='1');assert.equal(g.input.value,'retain value');}
 }
}
const source=readFileSync(new URL('../js/rentals.js',import.meta.url),'utf8');
const renderer=source.slice(source.indexOf('const state = day.admin_blocked'),source.indexOf('if (day.date === startInput.value'));
const render=day=>{
 const cell={className:'',disabled:false,attrs:{},setAttribute(k,v){this.attrs[k]=v;}};
 vm.runInNewContext(renderer,{day,cell});return cell;
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
 assert.equal(partial.className,'is-reserved');assert.equal(partial.disabled,true);assert.equal(partial.attrs['aria-label'],'2026-10-13: Customer reserved');
 const full=render({date:'2026-10-14',admin_blocked:false,past:false,available:false,reserved:3,remaining:0});
 assert.equal(full.className,'is-reserved');assert.equal(full.disabled,true);
}
const rangeSource=source.slice(source.indexOf('const rangeAvailable = async'),source.indexOf('const refreshCalendar = async'));
const monthOf=date=>`${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}`;
const checkRange=vm.runInNewContext(`${rangeSource}\nrangeAvailable`,{
 currentDetail:{id:'test'},quantityInput:{value:'1'},monthOf,
 getMonth:async month=>[
  {date:'2026-10-30',available:true,reserved:0},
  {date:'2026-10-31',available:true,reserved:1},
  {date:'2026-11-01',available:true,reserved:0},
  {date:'2026-11-02',available:true,reserved:0},
  {date:'2026-11-03',available:false,reserved:0}
 ].filter(day=>day.date.startsWith(month))
});
assert.equal(await checkRange('2026-10-30','2026-10-30'),true);
assert.equal(await checkRange('2026-10-31','2026-10-31'),false);
assert.equal(await checkRange('2026-10-30','2026-11-01'),false);
assert.equal(await checkRange('2026-11-01','2026-11-02'),true);
assert.equal(await checkRange('2026-11-01','2026-11-03'),false);
console.log('PASS: type controls restored; date labels unchanged in four timezones; partial/full reservations disabled; date ranges cannot cross reserved dates, including month boundaries.');
