import {readFileSync} from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

// Exercise the actual modal date-click handler, without a database or browser.
const source=readFileSync(new URL('../js/rentals.js',import.meta.url),'utf8');
const begin=source.indexOf("cell.addEventListener('click', async () => {");
const end=source.indexOf('days.append(cell);',begin);
assert.ok(begin>=0 && end>begin);
const handler=source.slice(begin,end);
const setup=()=>{
  const state={selectionRequest:0,startInput:{value:''},endInput:{value:''},quantityInput:{value:'1'},
    dialog:{open:true},calendar:{hidden:false},message:{textContent:''},refreshes:0,
    rangeCapacity:async()=>3,updateDateLabel(){},updateQuantityLimit(){}};
  state.refreshCalendar=()=>{state.refreshes++;};
  const context=vm.createContext(state);
  const click=date=>{
    let callback;
    state.day={date,remaining:3};
    state.cell={disabled:false,addEventListener(event,fn){assert.equal(event,'click');callback=fn;}};
    vm.runInContext(handler,context);
    return callback();
  };
  return {state,click};
};
{
  const {state,click}=setup();
  await click('2028-02-29'); await click('2028-02-29');
  assert.equal(state.startInput.value,'2028-02-29');
  assert.equal(state.endInput.value,'2028-02-29');
  assert.equal(state.calendar.hidden,false,'Completed same-day range must keep calendar visible.');
  await click('2028-03-02');
  assert.equal(state.endInput.value,'','Click after completed range begins a new range.');
  await click('2028-03-01');
  assert.equal(state.startInput.value,'2028-03-01','Earlier end selection resets start.');
  assert.equal(state.endInput.value,'');
  await click('2028-04-01');
  assert.equal(state.endInput.value,'2028-04-01');
  assert.equal(state.calendar.hidden,false);
}
{
  const {state,click}=setup();
  await click('2028-12-31');
  state.rangeCapacity=async()=>0;
  await click('2029-01-02');
  assert.equal(state.endInput.value,'','Blocked interior date must prevent completion.');
  assert.match(state.message.textContent,/unavailable date/);
  state.rangeCapacity=async()=>3;
  await click('2029-01-01');
  assert.equal(state.endInput.value,'2029-01-01');
}
{
  const {state,click}=setup();
  await click('2028-03-02');
  let resolve;
  state.rangeCapacity=()=>new Promise(done=>{resolve=done;});
  const pending=click('2028-03-05');
  await click('2028-03-01');
  resolve(3); await pending;
  assert.equal(state.startInput.value,'2028-03-01');
  assert.equal(state.endInput.value,'','Stale asynchronous end selection must not overwrite newer start.');
}
console.log('PASS: actual modal click handler; same-day selection; calendar remains visible; direct range changes; earlier start; leap/month/year boundaries; blocked range rejection; stale rapid-click response discarded.');
